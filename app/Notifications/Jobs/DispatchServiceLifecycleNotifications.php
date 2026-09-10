<?php

namespace App\Notifications\Jobs;

use App\Notifications\Events\NotificationRequested;
use App\Users\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DispatchServiceLifecycleNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function handle(): void
    {
        $noticeDate = now()->addDays(max(0, (int) config('service.expiration_notice_days', 1)))->toDateString();
        DB::table('service_user')->join('services', 'services.id', '=', 'service_user.service_id')
            ->where('services.is_active', true)
            ->where(function ($query) use ($noticeDate): void {
                $query->whereDate('service_user.expires_at', $noticeDate)
                    ->orWhereDate('service_user.expires_at', '<', now()->toDateString());
            })
            ->select('service_user.*', 'services.name as service_name')->orderBy('service_user.id')
            ->chunkById(100, function ($rows) use ($noticeDate): void {
                foreach ($rows as $row) {
                    $type = $row->expires_at === $noticeDate ? NotificationRequested::SERVICE_EXPIRING : NotificationRequested::SERVICE_EXPIRED;
                    $user = User::withoutGlobalScopes()->find($row->user_id);
                    if ($user?->active) NotificationRequested::dispatch($user, $type, "{$type}:{$row->id}:{$row->expires_at}", ['service' => $row->service_name, 'expires_at' => $row->expires_at]);
                }
            }, 'service_user.id', 'id');
    }
}
