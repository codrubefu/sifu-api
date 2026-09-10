<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ];
    }
}
