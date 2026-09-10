<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grade_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('grades', 'id')->where('organization_id', $this->user()?->organization_id)->whereNull('deleted_at'),
            ],
            'obtained_at' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
