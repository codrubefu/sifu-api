<?php

namespace App\Users\Http\Requests;

use App\Users\Support\OrganizationScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;

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
                OrganizationScopedExistsRule::make('grades', 'id', $this->user()?->organization_id)->whereNull('deleted_at'),
            ],
            'obtained_at' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
