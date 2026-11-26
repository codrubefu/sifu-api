<?php

namespace App\CheckIns\Services;

use App\Events\Models\EventOccurrence;
use App\Events\Services\EventParticipantService;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function __construct(
        private readonly EventParticipantService $participants,
        private readonly ServiceLifecycleService $serviceLifecycle,
        private readonly BusinessActivityLogger $activityLogger,
    ) {}

    public const MAX_CANDIDATES = 20;

    public function search(string $query, ?EventOccurrence $occurrence = null, ?int $userId = null): array
    {
        if ($userId !== null) {
            $member = User::query()->with(['activeServices', 'services'])->find($userId);

            return $member ? $this->verdict($member, $occurrence) : $this->notFound();
        }

        $query = trim($query);

        // Exact identifier (card scan, phone, email) wins immediately.
        $exact = User::query()
            ->with(['activeServices', 'services'])
            ->where(fn ($builder) => $builder->where('user_code', $query)->orWhere('phone', $query)->orWhere('email', $query))
            ->first();

        if ($exact) {
            return $this->verdict($exact, $occurrence);
        }

        $terms = array_values(array_filter(preg_split('/\s+/', $query) ?: [], fn (string $term): bool => $term !== ''));

        $matches = User::query()
            ->where(function ($builder) use ($terms): void {
                foreach ($terms as $term) {
                    $like = "%{$term}%";
                    $builder->where(fn ($inner) => $inner
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('user_code', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like));
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES + 1)
            ->get();

        if ($matches->isEmpty()) {
            return $this->notFound();
        }

        if ($matches->count() === 1) {
            return $this->verdict($matches->first(), $occurrence);
        }

        return [
            'member_found' => false,
            'verdict' => 'multiple_matches',
            'access_allowed' => false,
            'reason' => 'multiple_matches',
            'candidates' => $matches->take(self::MAX_CANDIDATES)->map(fn (User $user): array => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'user_code' => $user->user_code,
                'phone' => $user->phone,
                'email' => $user->email,
                'active' => (bool) $user->active,
            ])->values()->all(),
            'candidates_truncated' => $matches->count() > self::MAX_CANDIDATES,
        ];
    }

    private function notFound(): array
    {
        return [
            'member_found' => false,
            'verdict' => 'not_found',
            'access_allowed' => false,
            'reason' => 'member_not_found',
        ];
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
            $lockedOccurrence = EventOccurrence::query()->with('event.serviceRequirements.service')->lockForUpdate()->findOrFail($occurrence->id);
            if ($this->participants->isAlreadyRegistered($lockedOccurrence, $member->id)) {
                throw ValidationException::withMessages(['user_id' => 'already_present']);
            }

            if ($shouldConsumeAccess) {
                $this->serviceLifecycle->consumeEventAccess($member, $lockedOccurrence->event);
            }

            $lockedOccurrence->participants()->attach($member->id, [
                'status' => 'attended',
                'registered_at' => now(),
                'notes' => $notes,
                'access_override' => ! $shouldConsumeAccess,
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
            ->with(['event.category', 'event.serviceRequirements.service'])
            ->withCount('participants')
            ->whereDate('occurrence_date', now()->toDateString())
            ->where('status', 'scheduled')
            ->orderBy('start_datetime')
            ->get()
            ->all();
    }

    public const MAX_RECENT = 10;

    /**
     * Latest attended check-ins visible to the actor (organization + location scopes on User/EventOccurrence).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = self::MAX_RECENT): array
    {
        $limit = max(1, min($limit, self::MAX_RECENT));

        $rows = User::query()
            ->join('event_occurrence_user as check_in', 'check_in.user_id', '=', 'users.id')
            ->whereIn('check_in.event_occurrence_id', EventOccurrence::query()->select('id'))
            ->where('check_in.status', 'attended')
            ->orderByDesc('check_in.registered_at')
            ->orderByDesc('check_in.id')
            ->limit($limit)
            ->get([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.user_code',
                'check_in.event_occurrence_id',
                'check_in.registered_at as checked_in_at',
            ]);

        $titles = EventOccurrence::query()
            ->with('event:id,title')
            ->whereIn('id', $rows->pluck('event_occurrence_id')->unique()->all())
            ->get()
            ->mapWithKeys(fn (EventOccurrence $occurrence): array => [$occurrence->id => $occurrence->event?->title]);

        return $rows->map(fn (User $row): array => [
            'user_id' => $row->id,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'user_code' => $row->user_code,
            'occurrence_id' => (int) $row->event_occurrence_id,
            'event_title' => $titles[$row->event_occurrence_id] ?? null,
            'registered_at' => $row->checked_in_at,
        ])->values()->all();
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

        $occurrence->loadMissing('event.serviceRequirements.service');
        $result['occurrence'] = $occurrence;

        if ($occurrence->participants()->whereKey($member->id)->wherePivot('status', 'attended')->exists()) {
            return array_merge($result, ['verdict' => 'already_present', 'access_allowed' => false, 'reason' => 'already_present']);
        }

        if ($this->participants->requiresPayment($occurrence)) {
            return array_merge($result, ['verdict' => 'requires_payment', 'access_allowed' => false, 'reason' => 'requires_payment', 'requires_payment' => true]);
        }

        if (! $this->participants->isEligible($member, $occurrence)) {
            return array_merge($result, [
                'verdict' => 'refused',
                'access_allowed' => false,
                'reason' => 'missing_required_service',
                'missing_service_groups' => $this->participants->missingServiceGroups($member, $occurrence),
            ]);
        }

        if (! $this->participants->hasAvailableCapacity($occurrence)) {
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
