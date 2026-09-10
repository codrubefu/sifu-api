<?php

namespace App\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pivot_id' => $this->pivot?->id,
            'user_id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'status' => $this->pivot?->status,
            'registered_at' => $this->pivot?->registered_at,
            'notes' => $this->pivot?->notes,
            'created_at' => $this->pivot?->created_at,
            'updated_at' => $this->pivot?->updated_at,
        ];
    }
}
