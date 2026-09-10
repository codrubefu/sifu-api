<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'user_code' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'user_code')
                    ->where('organization_id', $user?->organization_id)
                    ->ignore($user?->id),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'phone')
                    ->where('organization_id', $user?->organization_id)
                    ->ignore($user?->id),
            ],
            'active' => ['sometimes', 'boolean'],
            'email' => [
                'sometimes',
                'required_without:parent_user_id',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where('organization_id', $user?->organization_id)
                    ->ignore($user?->id),
            ],
            'parent_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $user?->organization_id),
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if ($user && (int) $value === (int) $user->id) {
                        $fail('A user cannot be their own parent.');
                    }
                },
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
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
