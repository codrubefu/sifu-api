<?php

namespace App\CustomFields\Http\Requests;

use App\CustomFields\Models\CustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'entity_type' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('custom_fields', 'slug')
                    ->where('organization_id', $organizationId)
                    ->where('entity_type', $this->input('entity_type')),
            ],
            'type' => ['required', Rule::in(CustomField::TYPES)],
            'options' => ['nullable', 'array'],
            'validation_rules' => ['nullable', 'array'],
            'validation_rules.*' => ['string'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
