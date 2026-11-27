<?php

namespace App\Events\Models;

use App\Notifications\Events\NotificationRequested;
use App\Payments\Models\Payment;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EventParticipant extends Pivot
{
    public $incrementing = true;

    protected $table = 'event_occurrence_user';

    /** Participations that count as a payment obligation when the event requires payment. */
    public const BILLABLE_STATUSES = ['registered', 'attended'];

    protected $casts = ['access_override' => 'boolean', 'invoiced_at' => 'datetime'];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Payments targeting this participation (`payments.model_type = event_occurrence_user`). */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'model_id')
            ->where('model_type', Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER);
    }

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
