<?php

namespace App\Events\Jobs;

use App\Events\Models\Event;
use App\Events\Services\EventOccurrenceGeneratorService;
use App\Users\Exceptions\OrganizationLimitExceeded;
use App\Users\Services\OrganizationSubscriptionService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExtendRecurringEventOccurrences implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function handle(EventOccurrenceGeneratorService $generator): void
    {
        Event::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNotNull('occurrences_generated_until')
            ->whereIn('recurrence_type', ['weekly', 'monthly'])
            ->where('status', 'active')
            ->eachById(function (Event $event) use ($generator): void {
                // The outer transaction retains the organization lock while a failed inner
                // guard rolls back, so recording a block cannot race a successful retry.
                DB::transaction(function () use ($event, $generator): void {
                    if ($event->organization_id !== null) {
                        DB::table('organizations')->where('id', $event->organization_id)->lockForUpdate()->first();
                    }
                    try {
                        app(OrganizationSubscriptionService::class)->guard([$event->organization_id], function () use ($event, $generator): void {
                            $event->refresh();
                            if ($event->deleted_at !== null || $event->status !== 'active' || $event->occurrences_generated_until === null) {
                                DB::table('organization_event_limit_blocks')->where('event_id', $event->id)->delete();
                                return;
                            }
                            $horizon = $generator->resolveGenerationHorizon($event, Carbon::now());
                            if ($horizon !== null && $horizon->greaterThan($event->occurrences_generated_until)) {
                                $generator->generateOccurrences($event, $horizon, Carbon::parse($event->occurrences_generated_until));
                                $event->occurrences_generated_until = $horizon;
                                $event->save();
                            }
                            DB::table('organization_event_limit_blocks')->where('event_id', $event->id)->delete();
                        });
                    } catch (OrganizationLimitExceeded $exception) {
                        $previous = DB::table('organization_event_limit_blocks')->where('event_id', $event->id)->first();
                        if ($previous && json_decode($previous->details, true) === $exception->details) {
                            return;
                        }
                        DB::table('organization_event_limit_blocks')->updateOrInsert(['event_id' => $event->id], [
                            'organization_id' => $event->organization_id, 'details' => json_encode($exception->details),
                            'created_at' => $previous?->created_at ?? now(), 'updated_at' => now(),
                        ]);
                        DB::afterCommit(fn () => Log::warning('organization.event_generation.blocked', ['organization_id' => $event->organization_id, 'event_id' => $event->id] + $exception->details));
                    }
                });
            });
    }
}
