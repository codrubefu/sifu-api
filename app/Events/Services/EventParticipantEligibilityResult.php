<?php

namespace App\Events\Services;

/**
 * Outcome of an eligibility check for adding/reactivating a user as a
 * participant of an event occurrence.
 *
 * `requiresPayment` is purely informational: it reflects the parent event's
 * `requires_payment` flag and is set regardless of `ok`, so callers can
 * surface it to clients without it ever being a blocking reason on its own
 * (unlike `reason`, which is only set when `ok` is false and is meant to be
 * used by the caller to decide whether to reject the request).
 *
 * `missingServiceGroups` lists the indices of the unmet required service
 * groups when `reason` is `missing_required_service` (empty otherwise, and
 * also empty when the event only requires "any active service").
 *
 * `forced` is true when the result is allowed only because the caller asked
 * to force past a missing required service (`checkins.override`); such a
 * participation must not consume service accesses and is marked as an
 * exception (`access_override`). `missingServiceGroups` then lists what was skipped.
 */
final class EventParticipantEligibilityResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $reason,
        public readonly bool $requiresPayment,
        /** @var array<int, int> */
        public readonly array $missingServiceGroups = [],
        public readonly bool $forced = false,
    ) {
    }

    public static function allowed(bool $requiresPayment): self
    {
        return new self(true, null, $requiresPayment);
    }

    /**
     * @param array<int, int> $missingServiceGroups
     */
    public static function forced(bool $requiresPayment, array $missingServiceGroups): self
    {
        return new self(true, null, $requiresPayment, $missingServiceGroups, true);
    }

    /**
     * @param array<int, int> $missingServiceGroups
     */
    public static function rejected(string $reason, bool $requiresPayment = false, array $missingServiceGroups = []): self
    {
        return new self(false, $reason, $requiresPayment, $missingServiceGroups);
    }
}
