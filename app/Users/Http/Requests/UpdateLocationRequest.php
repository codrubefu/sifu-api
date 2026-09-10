<?php

namespace App\Users\Http\Requests;

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
            'location_group_id' => ['nullable', 'integer', 'exists:location_groups,id'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
