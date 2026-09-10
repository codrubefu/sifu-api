<?php

namespace App\Service\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startDate = $this->pivot?->start_date?->toDateString();
        $expiresAt = $this->pivot?->expires_at?->toDateString();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'price' => $this->price,
            'currency' => $this->currency,
            'duration_days' => $this->duration_days,
            'expiration_rule' => $this->expiration_rule,
            'fixed_expires_at' => $this->fixed_expires_at,
            'grace_period_days' => $this->grace_period_days,
            'max_accesses' => $this->max_accesses,
            'max_users' => $this->max_users,
            'is_active' => $this->is_active,
            'assignment_id' => $this->when($this->pivot !== null, $this->pivot?->id),
            'invoice_number' => $this->when($this->pivot !== null, $this->pivot?->invoice_number),
            'bill_number' => $this->when($this->pivot !== null, $this->pivot?->bill_number),
            'start_date' => $this->when($this->pivot !== null, $startDate),
            'expires_at' => $this->when($this->pivot !== null, $expiresAt),
            'status' => $this->when($this->pivot !== null, $this->pivot?->status),
            'accesses_used' => $this->when($this->pivot !== null, $this->pivot?->accesses_used),
            'suspended_at' => $this->when($this->pivot !== null, $this->pivot?->suspended_at),
            'resume_at' => $this->when($this->pivot !== null, $this->pivot?->resume_at),
            'status_reason' => $this->when($this->pivot !== null, $this->pivot?->status_reason),
            'activation_payment_id' => $this->when($this->pivot !== null, $this->pivot?->activation_payment_id),
            'is_currently_active' => $this->when(
                $this->pivot !== null,
                $this->is_active && $this->pivot?->status === 'active'
            ),
            'users_count' => $this->whenCounted('users'),
            'users' => $this->whenLoaded('users'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
