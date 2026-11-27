<?php

namespace App\Users\Http\Requests;

use App\Users\Support\OrganizationScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $location = $this->route('location');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('locations', 'name')->ignore($location?->id),
            ],
            'description' => ['nullable', 'string'],
            'location_group_id' => ['nullable', 'integer', OrganizationScopedExistsRule::make('location_groups', 'id', $this->user()?->organization_id)],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', OrganizationScopedExistsRule::make('users', 'id', $this->user()?->organization_id)],
        ];
    }
}
