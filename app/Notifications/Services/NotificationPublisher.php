<?php

namespace App\Notifications\Services;

use App\Notifications\Events\NotificationRequested;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NotificationPublisher
{
    /** Publish an urgent announcement to a deliberately scoped set of users. */
    public function urgent(Builder $recipients, string $announcementId, string $message): void
    {
        $recipients->where('active', true)->each(fn (User $user) => NotificationRequested::dispatch(
            $user,
            NotificationRequested::URGENT_ANNOUNCEMENT,
            "announcement.urgent:{$announcementId}",
            ['message' => $message],
        ));
    }
}
