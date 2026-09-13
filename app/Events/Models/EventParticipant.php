<?php

namespace App\Events\Models;

use App\Notifications\Events\NotificationRequested;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EventParticipant extends Pivot
{
    public $incrementing = true;

    protected $table = 'event_occurrence_user';

    protected static function booted(): void
    {
        static::created(function (self $participant): void {
            if (! in_array($participant->status, ['registered', 'attended'], true)) {
                return;
            }
            $occurrence = EventOccurrence::query()->findOrFail($participant->event_occurrence_id);
            $user = User::query()->findOrFail($participant->user_id);
            if ((int) $user->organization_id !== (int) $occurrence->organization_id) {
                return;
            }
            NotificationRequested::dispatch($user, 'event.attached', 'event.attached:'.$participant->id, ['event' => $occurrence->event->title, 'starts_at' => $occurrence->start_datetime?->toIso8601String()], ['mail']);
        });
    }
}
