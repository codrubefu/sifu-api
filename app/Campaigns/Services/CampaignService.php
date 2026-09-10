<?php

namespace App\Campaigns\Services;

use App\Campaigns\Models\Campaign;
use App\Notifications\Jobs\SendNotificationDelivery;
use App\Notifications\Models\NotificationDelivery;
use App\Reporting\Services\SegmentService;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function __construct(private readonly SegmentService $segments) {}

    public function recipients(Campaign $campaign): Builder
    {
        $query = $campaign->segment
            ? $this->segments->members($campaign->segment)
            : User::query()->where('organization_id', $campaign->organization_id);

        return $query->where('active', true);
    }

    public function schedule(Campaign $campaign, mixed $at): Campaign
    {
        abort_unless($campaign->status === 'draft', 409, 'Only draft campaigns can be scheduled.');
        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $at]);
        return $campaign;
    }

    public function cancel(Campaign $campaign): Campaign
    {
        abort_if(in_array($campaign->status, ['sent', 'cancelled'], true), 409, 'Campaign cannot be cancelled.');
        $campaign->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        return $campaign;
    }

    /** Expands the dynamic segment only when delivery becomes due. Safe to run repeatedly. */
    public function dispatch(Campaign $campaign): int
    {
        $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
        if ($campaign->status !== 'scheduled' || $campaign->scheduled_at?->isFuture()) return 0;

        $created = 0;
        DB::transaction(function () use ($campaign, &$created): void {
            $this->recipients($campaign)->eachById(function (User $user) use ($campaign, &$created): void {
                $delivery = NotificationDelivery::query()->firstOrCreate([
                    'event_key' => "campaign:{$campaign->id}", 'user_id' => $user->id, 'channel' => $campaign->channel,
                ], [
                    'campaign_id' => $campaign->id, 'event_type' => 'campaign', 'template' => 'campaign',
                    'payload' => ['message' => $campaign->content, 'subject' => $campaign->subject],
                    'consent_scope' => 'campaigns', 'status' => 'pending',
                ]);
                if ($delivery->wasRecentlyCreated) {
                    $created++;
                    SendNotificationDelivery::dispatch($delivery->id);
                }
            });
            $campaign->update(['status' => 'sent', 'dispatched_at' => now()]);
        });
        return $created;
    }

    public function statistics(Campaign $campaign): array
    {
        $counts = $campaign->deliveries()->selectRaw('status, count(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');
        return ['total' => $counts->sum(), 'pending' => (int) ($counts['pending'] ?? 0), 'sent' => (int) ($counts['sent'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0), 'skipped' => (int) ($counts['skipped'] ?? 0)];
    }
}
