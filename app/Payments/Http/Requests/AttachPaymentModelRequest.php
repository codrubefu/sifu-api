<?php

namespace App\Payments\Http\Requests;

use App\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachPaymentModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'model_type' => $this->input('model_type', Payment::MODEL_TYPE_SERVICE_USER),
        ]);
    }

    public function rules(): array
    {
        return [
            'model_type' => ['required', 'string', Rule::in(Payment::MODEL_TYPES)],
            'model_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
