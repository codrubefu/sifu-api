<?php

namespace App\Reporting\Services;

use App\Reporting\Models\Segment;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SegmentService
{
    /** @return array<int, int> */
    public function segmentIdsFor(User $user): array
    {
        return Segment::query()->where('organization_id', $user->organization_id)->get()
            ->filter(fn (Segment $segment) => $this->contains($segment, $user))
            ->modelKeys();
    }

    public function contains(Segment $segment, User $user): bool
    {
        if ((int) $segment->organization_id !== (int) $user->organization_id) {
            return false;
        }

        return $this->members($segment)->whereKey($user->getKey())->exists();
    }

    /**
     * Builds the tenant-safe member query reused by reports, announcements and campaigns.
     */
    public function members(Segment $segment): Builder
    {
        $criteria = $segment->criteria;
        $query = User::query()->where('users.organization_id', $segment->organization_id);

        if (array_key_exists('active', $criteria)) {
            $query->where('users.active', (bool) $criteria['active']);
        }
        if (isset($criteria['location_id'])) {
            $query->whereHas('locations', fn (Builder $q) => $q->whereKey($criteria['location_id']));
        }
        if (isset($criteria['service_type'])) {
            $query->whereHas('services', fn (Builder $q) => $q->where('services.type', $criteria['service_type']));
        }
        if (($criteria['expired'] ?? false) === true) {
            $query->whereHas('serviceAssignments', fn (Builder $q) => $q->where('expires_at', '<', now()));
        }
        if (isset($criteria['expires_in_days'])) {
            $query->whereHas('serviceAssignments', fn (Builder $q) => $q
                ->whereBetween('expires_at', [now(), now()->addDays((int) $criteria['expires_in_days'])]));
        }

        return $query->distinct();
    }
}
