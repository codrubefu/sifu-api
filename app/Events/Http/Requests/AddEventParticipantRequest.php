<?php

namespace App\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddEventParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['registered', 'attended', 'cancelled', 'no_show'])],
            'registered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
