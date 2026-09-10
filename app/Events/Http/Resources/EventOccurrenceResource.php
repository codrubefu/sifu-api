<?php

namespace App\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventOccurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $maxParticipants = $this->event?->max_participants;
        $participantsCount = $this->participants_count ?? $this->active_participants_count;

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event' => new EventResource($this->whenLoaded('event')),
            'occurrence_date' => $this->occurrence_date,
            'start_datetime' => $this->start_datetime,
            'end_datetime' => $this->end_datetime,
            'status' => $this->status,
            'participant_status' => $this->when($this->pivot !== null, $this->pivot?->status),
            'registered_at' => $this->when($this->pivot !== null, $this->pivot?->registered_at),
            'notes' => $this->when($this->pivot !== null, $this->pivot?->notes),
            'participants_count' => $this->when(isset($participantsCount), $participantsCount),
            'available_places' => $this->when($maxParticipants !== null && isset($participantsCount), max(0, $maxParticipants - $participantsCount)),
            'participants' => EventParticipantResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
