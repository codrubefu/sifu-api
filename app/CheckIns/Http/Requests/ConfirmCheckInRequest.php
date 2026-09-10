<?php

namespace App\CheckIns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmCheckInRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'occurrence_id' => ['required', 'integer', 'exists:event_occurrences,id'],
            'allow_override' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
