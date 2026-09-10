<?php

namespace App\Events\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'location_id' => $this->location_id,
            'instructor_id' => $this->instructor_id,
            'group_id' => $this->group_id,
            'category' => new EventCategoryResource($this->whenLoaded('category')),
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_days' => $this->recurrence_days,
            'monthly_day' => $this->monthly_day,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'requires_active_service' => $this->requires_active_service,
            'required_service_id' => $this->required_service_id,
            'requires_payment' => $this->requires_payment,
            'payment_amount' => $this->payment_amount,
            'payment_type' => $this->payment_type,
            'required_service' => $this->whenLoaded('requiredService'),
            'max_participants' => $this->max_participants,
            'status' => $this->status,
            'occurrences_count' => $this->whenCounted('occurrences'),
            'occurrences' => EventOccurrenceResource::collection($this->whenLoaded('occurrences')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
