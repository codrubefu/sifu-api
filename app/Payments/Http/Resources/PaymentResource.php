<?php

namespace App\Payments\Http\Resources;

use App\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'payment_type_id' => $this->payment_type_id,
            'payment_type' => $this->paymentTypeName(),
            'organization_id' => $this->organization_id,
            'location_id' => $this->location_id,
            'status' => $this->status,
            'external_reference' => $this->external_reference,
            'receipt_number' => $this->receipt_number,
            'provider' => $this->provider,
            'provider_transaction_id' => $this->provider_transaction_id,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'amount' => $this->amount,
            'paid_at' => $this->paid_at,
            'confirmed_at' => $this->confirmed_at,
            'failed_at' => $this->failed_at,
            'refunded_at' => $this->refunded_at,
            'cancelled_at' => $this->cancelled_at,
            'failure_reason' => $this->failure_reason,
            'admin_id' => $this->admin_id,
            'admin' => new UserResource($this->whenLoaded('admin')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
