<?php

namespace App\Users\Http\Requests;

use App\Users\Support\OrganizationScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:locations,name'],
            'description' => ['nullable', 'string'],
            'location_group_id' => ['nullable', 'integer', OrganizationScopedExistsRule::make('location_groups', 'id', $this->user()?->organization_id)],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', OrganizationScopedExistsRule::make('users', 'id', $this->user()?->organization_id)],
        ];
    }
}
