<?php

namespace App\Users\Http\Requests;

use App\Users\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', PasswordPolicy::for($this->user()?->hasAnyRight(['users.manage', 'rights.manage']) ? 'administrator' : 'operator'), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
