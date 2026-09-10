<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grade_id' => [
                'required', 'integer',
                Rule::exists('grades', 'id')->where('organization_id', $this->user()?->organization_id)->whereNull('deleted_at'),
            ],
            'obtained_at' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
