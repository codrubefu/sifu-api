<?php

namespace App\CheckIns\Http\Resources;

use App\Events\Http\Resources\EventOccurrenceResource;
use App\Events\Http\Resources\EventParticipantResource;
use App\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'member_found' => (bool) ($this->resource['member_found'] ?? false),
            'member' => isset($this->resource['member']) ? new UserResource($this->resource['member']) : null,
            'verdict' => $this->resource['verdict'] ?? null,
            'access_allowed' => (bool) ($this->resource['access_allowed'] ?? false),
            'reason' => $this->resource['reason'] ?? null,
            'requires_payment' => (bool) ($this->resource['requires_payment'] ?? false),
            'document_expired' => (bool) ($this->resource['document_expired'] ?? false),
            'active_subscription' => (bool) ($this->resource['active_subscription'] ?? false),
            'eligible_services' => $this->resource['eligible_services'] ?? [],
            'last_check_in' => $this->resource['last_check_in'] ?? null,
            'occurrence' => isset($this->resource['occurrence']) ? new EventOccurrenceResource($this->resource['occurrence']) : null,
            'participant' => isset($this->resource['participant']) ? new EventParticipantResource($this->resource['participant']) : null,
        ];
    }
}
