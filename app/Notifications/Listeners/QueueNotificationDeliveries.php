<?php

namespace App\Notifications\Listeners;

use App\Notifications\Events\NotificationRequested;
use App\Notifications\Jobs\SendNotificationDelivery;
use App\Notifications\Models\NotificationDelivery;

class QueueNotificationDeliveries
{
    public function handle(NotificationRequested $event): void
    {
        foreach (['sms', 'mail', 'push'] as $channel) {
            if (! $event->user->consentsTo($channel)) {
                continue;
            }

            $delivery = NotificationDelivery::query()->firstOrCreate([
                'event_key' => $event->key,
                'user_id' => $event->user->getKey(),
                'channel' => $channel,
            ], [
                'event_type' => $event->type,
                'template' => $event->type,
                'payload' => $event->payload,
                'status' => 'pending',
            ]);

            if ($delivery->wasRecentlyCreated) {
                SendNotificationDelivery::dispatch($delivery->getKey());
            }
        }
    }
}
