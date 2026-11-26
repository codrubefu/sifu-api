<?php

namespace App\Events\Http\Resources;

use App\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * Quick-add candidate for an occurrence: the regular user payload plus the
 * eligibility verdict against the event's required service groups.
 */
class EventEligibleParticipantResource extends UserResource
{
    /**
     * @param  array<int, int>  $missingServiceGroups
     */
    public function __construct($resource, private readonly bool $eligible = true, private readonly array $missingServiceGroups = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'eligible' => $this->eligible,
            'missing_service_groups' => array_values($this->missingServiceGroups),
        ];
    }
}
