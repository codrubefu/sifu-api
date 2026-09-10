<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Users\Support\PasswordPolicy;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'user_code')->where('organization_id', $this->user()?->organization_id),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'phone')->where('organization_id', $this->user()?->organization_id),
            ],
            'active' => ['sometimes', 'boolean'],
            'email' => [
                'required_without:parent_user_id',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('organization_id', $this->user()?->organization_id),
            ],
            'parent_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $this->user()?->organization_id),
            ],
            'password' => ['sometimes', 'nullable', 'string', PasswordPolicy::for($this->is('api/administrators*') ? 'administrator' : 'operator')],
            'notification_consents' => ['sometimes', 'array:sms,mail,push'],
            'notification_consents.*' => ['boolean'],
            'push_token' => ['nullable', 'string', 'max:2048'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'location_ids' => ['sometimes', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'services' => ['sometimes', 'array'],
            'services.*.id' => ['required_with:services', 'integer', 'exists:services,id'],
            'services.*.start_date' => ['sometimes', 'date'],
        ];
    }
}
