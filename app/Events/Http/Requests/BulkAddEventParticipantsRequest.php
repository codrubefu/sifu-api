<?php

namespace App\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAddEventParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['registered', 'attended', 'cancelled', 'no_show'])],
            'registered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
