<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_ids' => ['required_without:services', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'services' => ['sometimes', 'array'],
            'services.*.id' => ['required_with:services', 'integer', 'exists:services,id'],
            'services.*.start_date' => ['sometimes', 'date'],
        ];
    }
}
