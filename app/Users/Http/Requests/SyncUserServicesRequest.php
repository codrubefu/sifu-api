<?php

namespace App\Users\Http\Requests;

use App\Users\Support\OrganizationScopedExistsRule;
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
            'service_ids.*' => ['integer', OrganizationScopedExistsRule::make('services', 'id', $this->user()?->organization_id)],
            'services' => ['sometimes', 'array'],
            'services.*.id' => ['required_with:services', 'integer', OrganizationScopedExistsRule::make('services', 'id', $this->user()?->organization_id)],
            'services.*.start_date' => ['sometimes', 'date'],
        ];
    }
}
