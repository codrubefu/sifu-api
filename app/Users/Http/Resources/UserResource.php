<?php

namespace App\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_user_id' => $this->parent_user_id,
            'user_code' => $this->user_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'notification_consents' => $this->notification_consents ?? [],
            'push_token' => $this->push_token,
            'active' => $this->active,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'parent' => new UserResource($this->whenLoaded('parent')),
            'groups' => $this->whenLoaded('groups'),
            'locations' => $this->whenLoaded('locations'),
            'services' => $this->whenLoaded('services'),
            'service_history' => $this->whenLoaded('services', function (): array {
                return $this->services->map(function ($service): array {
                    $pivot = $service->pivot;
                    $status = $pivot?->status ?? 'pending';

                    return [
                        'id' => $pivot?->id,
                        'service_id' => $service->id,
                        'name' => $service->name,
                        'invoice_number' => $pivot?->invoice_number,
                        'bill_number' => $pivot?->bill_number,
                        'start_date' => $pivot?->start_date?->toDateString(),
                        'expires_at' => $pivot?->expires_at?->toDateString(),
                        'status' => $status,
                        'accesses_used' => $pivot?->accesses_used,
                        'suspended_at' => $pivot?->suspended_at,
                        'resume_at' => $pivot?->resume_at,
                        'status_reason' => $pivot?->status_reason,
                        'activation_payment_id' => $pivot?->activation_payment_id,
                        'is_active' => $service->is_active && $status === 'active',
                        'is_currently_active' => $service->is_active && $status === 'active',
                    ];
                })->all();
            }),
            'active_grade' => $this->whenLoaded('activeUserGrade', fn () => $this->activeUserGrade?->grade),
            'grade_history' => UserGradeResource::collection($this->whenLoaded('userGrades')),
            'active_services' => $this->whenLoaded('activeServices'),
            'has_active_service' => $this->whenLoaded(
                'activeServices',
                fn () => $this->activeServices->isNotEmpty()
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
