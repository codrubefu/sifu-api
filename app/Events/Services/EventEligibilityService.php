<?php

namespace App\Events\Services;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Users\Models\User;

/**
 * Active-service eligibility for events with service groups:
 * every group must be satisfied (AND) and a group is satisfied by any one
 * of its services being active for the user (OR). An event that requires an
 * active service but defines no groups accepts any active service.
 */
class EventEligibilityService
{
    public function canUserJoinOccurrence(User $user, EventOccurrence $occurrence): bool
    {
        return $this->canUserJoinEvent($user, $this->freshEvent($occurrence));
    }

    public function canUserJoinEvent(User $user, Event $event): bool
    {
        if (! $event->requires_active_service) {
            return true;
        }

        $event->loadMissing('serviceRequirements.service');
        $groups = $event->serviceGroupIds();

        if ($groups === []) {
            return $this->hasActiveService($user);
        }

        return $this->unmetGroups($groups, $this->activeServiceIds($user)) === [];
    }

    /**
     * Indices (matching the `service_groups` API field) of the groups the user
     * does not satisfy. Empty when the event has no service requirement or no groups.
     *
     * @return array<int, int>
     */
    public function missingServiceGroups(User $user, Event $event): array
    {
        if (! $event->requires_active_service) {
            return [];
        }

        $event->loadMissing('serviceRequirements.service');
        $groups = $event->serviceGroupIds();

        return $groups === [] ? [] : $this->unmetGroups($groups, $this->activeServiceIds($user));
    }

    /**
     * @return array<int, int>
     */
    public function missingServiceGroupsForOccurrence(User $user, EventOccurrence $occurrence): array
    {
        return $this->missingServiceGroups($user, $this->freshEvent($occurrence));
    }

    /**
     * Eligibility computed from the user's already loaded `activeServices`
     * relation (no extra query per user), for paginated lists. `missing_service_groups`
     * stays empty when the event only requires "any active service".
     *
     * @return array{eligible: bool, missing_service_groups: array<int, int>}
     */
    public function evaluateWithLoadedServices(User $user, Event $event): array
    {
        if (! $event->requires_active_service) {
            return ['eligible' => true, 'missing_service_groups' => []];
        }

        $activeServiceIds = $user->activeServices->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $groups = $event->serviceGroupIds();

        if ($groups === []) {
            return ['eligible' => $activeServiceIds !== [], 'missing_service_groups' => []];
        }

        $missing = $this->unmetGroups($groups, $activeServiceIds);

        return ['eligible' => $missing === [], 'missing_service_groups' => $missing];
    }

    public function hasActiveService(User $user): bool
    {
        return $user->activeServices()->exists();
    }

    private function freshEvent(EventOccurrence $occurrence): Event
    {
        return $occurrence->event()->with('serviceRequirements.service')->firstOrFail();
    }

    /**
     * @return array<int, int>
     */
    private function activeServiceIds(User $user): array
    {
        return $user->activeServices()->pluck('services.id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  array<int, array<int, int>>  $groups
     * @param  array<int, int>  $activeServiceIds
     * @return array<int, int>
     */
    private function unmetGroups(array $groups, array $activeServiceIds): array
    {
        $missing = [];
        foreach ($groups as $index => $serviceIds) {
            if (array_intersect($serviceIds, $activeServiceIds) === []) {
                $missing[] = $index;
            }
        }

        return $missing;
    }
}
