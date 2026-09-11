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
 */
final class EventParticipantEligibilityResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $reason,
        public readonly bool $requiresPayment,
    ) {
    }

    public static function allowed(bool $requiresPayment): self
    {
        return new self(true, null, $requiresPayment);
    }

    public static function rejected(string $reason, bool $requiresPayment = false): self
    {
        return new self(false, $reason, $requiresPayment);
    }
}
