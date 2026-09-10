<?php

namespace App\Reporting\Http\Requests;

use App\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'organization_id' => ['sometimes', 'integer'], 'location_id' => ['sometimes', 'integer'],
            'admin_id' => ['sometimes', 'integer'],
            'payment_type_id' => ['sometimes', 'integer', Rule::in(array_keys(Payment::PAYMENT_TYPES))],
            'status' => ['sometimes', Rule::in(Payment::STATUSES)],
            'service_type' => ['sometimes', 'string', 'max:100'],
            'service_id' => ['sometimes', 'integer'],
            'member_id' => ['sometimes', 'integer'],
            'group_by' => ['sometimes', Rule::in(['day', 'month'])],
            'segment_id' => ['sometimes', 'integer'],
        ];
    }
}
