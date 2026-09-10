<?php

namespace App\CheckIns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchCheckInMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:120'],
            'occurrence_id' => ['nullable', 'integer', 'exists:event_occurrences,id'],
        ];
    }
}
