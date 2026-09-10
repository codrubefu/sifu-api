<?php

namespace App\CheckIns\Services;

use App\Events\Models\EventOccurrence;
use App\Events\Services\EventEligibilityService;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function __construct(
        private readonly EventEligibilityService $eligibility,
        private readonly ServiceLifecycleService $serviceLifecycle,
        private readonly BusinessActivityLogger $activityLogger,
    ) {}

    public function search(string $query, ?EventOccurrence $occurrence = null): array
    {
        $member = User::query()
            ->with(['activeServices', 'services'])
            ->where(function ($builder) use ($query): void {
                $builder->where('user_code', $query)
                    ->orWhere('phone', $query)
                    ->orWhere('email', $query)
                    ->orWhere('user_code', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderByRaw('case when user_code = ? or phone = ? or email = ? then 0 else 1 end', [$query, $query, $query])
            ->first();

        if (! $member) {
            return [
                'member_found' => false,
                'verdict' => 'not_found',
                'access_allowed' => false,
                'reason' => 'member_not_found',
            ];
        }

        return $this->verdict($member, $occurrence);
    }

    public function confirm(User $member, EventOccurrence $occurrence, User $actor, bool $allowOverride = false, ?string $notes = null): array
    {
        $verdict = $this->verdict($member, $occurrence);

        if ($verdict['verdict'] === 'already_present') {
            $this->log(AuditLog::CHECKIN_REFUSED, $member, $occurrence, $actor, $verdict);

            return $verdict;
        }

        $shouldConsumeAccess = (bool) $verdict['access_allowed'];

        if (! $verdict['access_allowed']) {
            if (! $allowOverride || ! $actor->hasRight('checkins.override')) {
                $this->log(AuditLog::CHECKIN_REFUSED, $member, $occurrence, $actor, $verdict);

                throw ValidationException::withMessages(['access' => $verdict['reason'] ?? 'access_refused']);
            }

            $verdict['verdict'] = 'override_allowed';
            $verdict['access_allowed'] = true;
            $verdict['reason'] = 'override_allowed';
        }

        $participant = DB::transaction(function () use ($member, $occurrence, $notes, $shouldConsumeAccess): User {
            $lockedOccurrence = EventOccurrence::query()->with('event.requiredService')->lockForUpdate()->findOrFail($occurrence->id);
            if ($lockedOccurrence->participants()->whereKey($member->id)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'already_present']);
            }

            if ($shouldConsumeAccess) {
                $this->serviceLifecycle->consumeEventAccess($member, $lockedOccurrence->event);
            }

            $lockedOccurrence->participants()->attach($member->id, [
                'status' => 'attended',
                'registered_at' => now(),
                'notes' => $notes,
            ]);

            return $lockedOccurrence->participants()->whereKey($member->id)->firstOrFail();
        });

        $result = $this->verdict($member->refresh(), $occurrence->refresh());
        $result['verdict'] = $allowOverride && ! $shouldConsumeAccess ? 'override_allowed' : 'allowed';
        $result['access_allowed'] = true;
        $result['participant'] = $participant;

        $this->log(AuditLog::CHECKIN_ACCEPTED, $member, $occurrence, $actor, $result);

        return $result;
    }

    public function currentOccurrences(User $actor): array
    {
        return EventOccurrence::query()
            ->with(['event.category', 'event.requiredService'])
            ->withCount('participants')
            ->whereDate('occurrence_date', now()->toDateString())
            ->where('status', 'scheduled')
            ->orderBy('start_datetime')
            ->get()
            ->all();
    }

    private function verdict(User $member, ?EventOccurrence $occurrence): array
    {
        $member->loadMissing(['activeServices', 'services', 'documents']);
        $lastCheckIn = $this->lastCheckIn($member);
        $documentExpired = $member->documents()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->exists();

        $result = [
            'member_found' => true,
            'member' => $member,
            'verdict' => 'allowed',
            'access_allowed' => true,
            'reason' => null,
            'requires_payment' => false,
            'document_expired' => $documentExpired,
            'active_subscription' => $member->activeServices->isNotEmpty(),
            'eligible_services' => $member->activeServices->map(fn ($service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'status' => $service->pivot?->status,
                'accesses_used' => $service->pivot?->accesses_used,
                'max_accesses' => $service->max_accesses,
                'expires_at' => $service->pivot?->expires_at,
            ])->values()->all(),
            'last_check_in' => $lastCheckIn,
        ];

        if (! $member->active) {
            return array_merge($result, ['verdict' => 'refused', 'access_allowed' => false, 'reason' => 'member_inactive']);
        }

        if ($documentExpired) {
            return array_merge($result, ['verdict' => 'document_expired', 'access_allowed' => false, 'reason' => 'document_expired']);
        }

        if (! $occurrence) {
            return $result;
        }

        $occurrence->loadMissing('event.requiredService');
        $result['occurrence'] = $occurrence;

        if ($occurrence->participants()->whereKey($member->id)->wherePivot('status', 'attended')->exists()) {
            return array_merge($result, ['verdict' => 'already_present', 'access_allowed' => false, 'reason' => 'already_present']);
        }

        if ($occurrence->event->requires_payment) {
            return array_merge($result, ['verdict' => 'requires_payment', 'access_allowed' => false, 'reason' => 'requires_payment', 'requires_payment' => true]);
        }

        if (! $this->eligibility->canUserJoinOccurrence($member, $occurrence)) {
            return array_merge($result, ['verdict' => 'refused', 'access_allowed' => false, 'reason' => 'missing_required_service']);
        }

        $maxParticipants = $occurrence->event->max_participants;
        if ($maxParticipants !== null && $occurrence->activeParticipants()->count() >= $maxParticipants) {
            return array_merge($result, ['verdict' => 'refused', 'access_allowed' => false, 'reason' => 'capacity_full']);
        }

        return $result;
    }

    private function lastCheckIn(User $member): ?array
    {
        $occurrence = $member->eventOccurrences()
            ->with('event')
            ->wherePivot('status', 'attended')
            ->orderByPivot('registered_at', 'desc')
            ->first();

        if (! $occurrence) {
            return null;
        }

        return [
            'occurrence_id' => $occurrence->id,
            'event_title' => $occurrence->event?->title,
            'registered_at' => $occurrence->pivot?->registered_at,
            'status' => $occurrence->pivot?->status,
        ];
    }

    private function log(string $eventType, User $member, EventOccurrence $occurrence, User $actor, array $payload): void
    {
        $this->activityLogger->record($eventType, $member, $occurrence, [], [
            'occurrence_id' => $occurrence->id,
            'verdict' => $payload['verdict'] ?? null,
            'reason' => $payload['reason'] ?? null,
        ], $actor);
    }
}
