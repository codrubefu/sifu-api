<?php

namespace App\Users\Http\Requests;

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
            'location_group_id' => ['nullable', 'integer', 'exists:location_groups,id'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
