<?php

namespace App\Users\Http\Requests;

use App\Users\Models\User;
use App\Users\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'password' => ['required', 'string', PasswordPolicy::for($this->targetAccountType()), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    private function targetAccountType(): string
    {
        $user = User::query()
            ->where('email', $this->string('email')->toString())
            ->where('organization_id', $this->input('organization_id'))
            ->first();

        return $user?->hasAnyRight(['users.manage', 'rights.manage']) ? 'administrator' : 'operator';
    }
}
