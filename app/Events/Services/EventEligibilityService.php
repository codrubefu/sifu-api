<?php

namespace App\Events\Services;

use App\Events\Models\EventOccurrence;
use App\Service\Models\Service;
use App\Users\Models\User;

class EventEligibilityService
{
    public function canUserJoinOccurrence(User $user, EventOccurrence $occurrence): bool
    {
        $event = $occurrence->event()->with('requiredService')->firstOrFail();

        if (! $event->requires_active_service) {
            return true;
        }

        return $this->hasActiveService($user, $event->requiredService);
    }

    public function hasActiveService(User $user, ?Service $requiredService = null): bool
    {
        $query = $user->activeServices();

        if ($requiredService) {
            $query->where('services.id', $requiredService->id);
        }

        return $query->exists();
    }
}
