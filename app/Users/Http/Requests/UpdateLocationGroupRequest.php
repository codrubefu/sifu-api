<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $locationGroup = $this->route('locationGroup');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('location_groups', 'name')
                    ->where('organization_id', $this->user()?->organization_id)
                    ->ignore($locationGroup?->id),
            ],
            'description' => ['nullable', 'string'],
        ];
    }
}
