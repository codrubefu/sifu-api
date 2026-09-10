<?php

namespace App\Users\Jobs;

use App\Users\Models\GdprExport;
use App\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePersonalDataExport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $exportId) {}

    public function handle(): void
    {
        $export = GdprExport::query()->findOrFail($this->exportId);

        try {
            // Both predicates are deliberate: never trust a queued user id without its tenant.
            $user = User::query()->withoutGlobalScopes()->where('organization_id', $export->organization_id)
                ->whereKey($export->user_id)->firstOrFail();
            $data = [
                'profile' => $user->only(['id', 'user_code', 'first_name', 'last_name', 'phone', 'email', 'active', 'created_at', 'updated_at']),
                'consents' => $user->consentRecords()->orderBy('occurred_at')->get()->makeHidden(['actor_id'])->toArray(),
                'services' => $user->serviceAssignments()->with('service:id,name')->get()->toArray(),
                'payments' => $user->registeredPayments()->where('organization_id', $export->organization_id)
                    ->get()->makeHidden(['provider_payload'])->toArray(),
            ];
            $path = "gdpr/{$export->organization_id}/{$export->id}.json";
            Storage::disk($export->disk)->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $export->update(['status' => 'ready', 'path' => $path, 'expires_at' => now()->addHours(24)]);
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'failure_reason' => mb_substr($exception->getMessage(), 0, 1000)]);
            throw $exception;
        }
    }
}
