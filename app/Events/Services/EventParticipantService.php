<?php

namespace App\Events\Services;

use App\Events\Models\EventOccurrence;
use App\Users\Models\User;

/**
 * Centralizes the checks needed to decide whether a user can be added (or
 * reactivated) as a participant of an event occurrence: duplicate
 * registration, active-service eligibility, capacity, and the informational
 * requires_payment signal.
 *
 * Each check is also exposed individually so callers with different
 * semantics (e.g. CheckInService, which has its own narrower "already
 * present" concept, or bulk operations that need a batch-level duplicate/
 * capacity check) can compose them without duplicating the underlying
 * query logic.
 */
class EventParticipantService
{
    public function __construct(
        private readonly EventEligibilityService $eligibility,
    ) {
    }

    /**
     * Single-pass check used when adding one brand-new participant: duplicate
     * registration, active-service eligibility, capacity, and the
     * informational requires_payment flag. Duplicate/eligibility/capacity are
     * blocking (reflected in `ok`/`reason`); requires_payment never blocks
     * here, it is only informational.
     */
    public function evaluate(User $user, EventOccurrence $occurrence): EventParticipantEligibilityResult
    {
        $occurrence->loadMissing('event.requiredService');
        $requiresPayment = $this->requiresPayment($occurrence);

        if ($this->isAlreadyRegistered($occurrence, $user->id)) {
            return EventParticipantEligibilityResult::rejected('already_registered', $requiresPayment);
        }

        if (! $this->isEligible($user, $occurrence)) {
            return EventParticipantEligibilityResult::rejected('missing_required_service', $requiresPayment);
        }

        if (! $this->hasAvailableCapacity($occurrence)) {
            return EventParticipantEligibilityResult::rejected('capacity_full', $requiresPayment);
        }

        return EventParticipantEligibilityResult::allowed($requiresPayment);
    }

    /**
     * True when the given user (or any of the given user IDs) already has a
     * participant pivot row for this occurrence, regardless of status.
     */
    public function isAlreadyRegistered(EventOccurrence $occurrence, int|array $userId): bool
    {
        return $occurrence->participants()->whereKey($userId)->exists();
    }

    public function isEligible(User $user, EventOccurrence $occurrence): bool
    {
        return $this->eligibility->canUserJoinOccurrence($user, $occurrence);
    }

    /**
     * True when the occurrence has room for `$additionalCount` more active
     * participants (status `registered`/`attended`), given the event's
     * `max_participants` limit.
     */
    public function hasAvailableCapacity(EventOccurrence $occurrence, int $additionalCount = 1): bool
    {
        $occurrence->loadMissing('event');
        $maxParticipants = $occurrence->event->max_participants;

        if ($maxParticipants === null) {
            return true;
        }

        return ($occurrence->activeParticipants()->count() + $additionalCount) <= $maxParticipants;
    }

    /**
     * Informational only: whether the parent event requires payment. Never a
     * blocking condition by itself.
     */
    public function requiresPayment(EventOccurrence $occurrence): bool
    {
        $occurrence->loadMissing('event');

        return (bool) $occurrence->event->requires_payment;
    }
}
