<?php

namespace App\Service\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'string', 'in:membership,access_pass'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:3'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'expiration_rule' => ['sometimes', 'string', 'in:duration,fixed_date,none'],
            'fixed_expires_at' => ['nullable', 'date', 'required_if:expiration_rule,fixed_date'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'max_accesses' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
