<?php

namespace App\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'location_id' => ['sometimes', 'integer', 'min:1'],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'instructor_id' => ['sometimes', 'integer', 'min:1'],
            'group_id' => ['sometimes', 'integer', 'min:1'],
            'member_id' => ['sometimes', 'integer', 'min:1'],
            'format' => ['sometimes', Rule::in(['csv', 'xlsx'])],
        ];
    }
}
