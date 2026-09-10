<?php

namespace App\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventParticipationReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'organization_id' => ['sometimes', 'integer'],
            'category_id' => ['sometimes', 'integer'],
            'location' => ['sometimes', 'string', 'max:255'],
            'time_from' => ['sometimes', 'date_format:H:i'],
            'time_to' => ['sometimes', 'date_format:H:i', 'after:time_from'],
            'underutilized_below' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
