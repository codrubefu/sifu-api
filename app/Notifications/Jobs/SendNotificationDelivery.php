<?php

namespace App\Notifications\Jobs;

use App\Notifications\Models\NotificationDelivery;
use App\Notifications\Services\NotificationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNotificationDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;
    public array $backoff = [60, 300, 900];

    public function __construct(public int $deliveryId) {}

    public function handle(NotificationSender $sender): void
    {
        $delivery = NotificationDelivery::query()->with('user')->findOrFail($this->deliveryId);
        if (in_array($delivery->status, ['sent', 'skipped'], true)) return;

        $scope = $delivery->consent_scope ?: 'all';
        if (! $delivery->user->consentsToScope($delivery->channel, $scope)) {
            $delivery->update(['status' => 'skipped', 'skip_reason' => 'consent']);
            return;
        }

        $attemptNumber = $delivery->attempts()->count() + 1;
        $attempt = $delivery->attempts()->create([
            'template' => $delivery->template,
            'channel' => $delivery->channel,
            'provider' => $this->provider($delivery->channel),
            'status' => 'processing',
            'attempt' => $attemptNumber,
        ]);

        try {
            $result = $sender->send($delivery);
            $attempt->update(['provider' => $result['provider'], 'external_id' => $result['external_id'], 'status' => 'sent']);
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $attempt->update(['status' => 'failed', 'error' => $exception->getMessage()]);
            $delivery->update(['status' => 'failed']);
            throw $exception;
        }
    }

    private function provider(string $channel): string
    {
        return match ($channel) { 'sms' => 'smsportal', 'mail' => (string) config('mail.default'), 'push' => 'push', default => 'unknown' };
    }
}
