<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUserServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_ids' => [
                Rule::requiredIf(fn (): bool => ! $this->has('services') && ! $this->has('service_ids')),
                'array',
            ],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'services' => ['sometimes', 'array'],
            'services.*.id' => ['required_with:services', 'integer', 'exists:services,id'],
            'services.*.start_date' => ['sometimes', 'date'],
        ];
    }
}
