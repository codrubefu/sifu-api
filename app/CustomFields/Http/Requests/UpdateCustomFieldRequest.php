<?php

namespace App\CustomFields\Http\Requests;

use App\CustomFields\Models\CustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $field = $this->route('customField');
        $organizationId = $this->user()?->organization_id;
        $entityType = $this->input('entity_type', $field?->entity_type);

        return [
            'entity_type' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('custom_fields', 'slug')
                    ->ignore($field?->id)
                    ->where('organization_id', $organizationId)
                    ->where('entity_type', $entityType),
            ],
            'type' => ['sometimes', Rule::in(CustomField::TYPES)],
            'options' => ['nullable', 'array'],
            'validation_rules' => ['nullable', 'array'],
            'validation_rules.*' => ['string'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
