<?php

namespace App\Notifications\Events;

use App\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationRequested
{
    use Dispatchable, SerializesModels;

    public const SERVICE_ACTIVATED = 'service.activated';
    public const SERVICE_EXPIRING = 'service.expiring';
    public const SERVICE_EXPIRED = 'service.expired';
    public const SCHEDULE_CHANGED = 'schedule.changed';
    public const URGENT_ANNOUNCEMENT = 'announcement.urgent';
    public const RESUMED = 'activity.resumed';

    public function __construct(
        public User $user,
        public string $type,
        public string $key,
        public array $payload = [],
    ) {}
}
