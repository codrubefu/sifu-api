<?php

namespace App\Events\Http\Resources;

use App\Payments\Models\Payment;
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
            // True when admitted past the access checks (check-in override or forced add) without consuming an access.
            'access_override' => (bool) ($this->pivot?->access_override ?? false),
            // On-demand invoice (paid events): null until POST .../participants/{user}/invoice.
            'invoice_number' => $this->pivot?->invoice_number,
            'invoiced_at' => $this->pivot?->invoiced_at?->toIso8601String(),
            // Most relevant payment for this participation (latest confirmed, else latest); only when loaded.
            'payment' => $this->when($this->pivot?->relationLoaded('payments') ?? false, fn () => $this->paymentSummary()),
            'created_at' => $this->pivot?->created_at,
            'updated_at' => $this->pivot?->updated_at,
        ];
    }

    private function paymentSummary(): ?array
    {
        $payments = $this->pivot->payments;
        $payment = $payments->where('status', Payment::STATUS_CONFIRMED)->sortByDesc(fn (Payment $p) => [$p->paid_at?->timestamp ?? 0, $p->id])->first()
            ?? $payments->sortByDesc('id')->first();

        if (! $payment) {
            return null;
        }

        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'receipt_number' => $payment->receipt_number,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }
}
