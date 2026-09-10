<?php

namespace App\Reporting\Http\Requests;

use App\Service\Services\ServiceLifecycleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceExpirationReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'location_id' => ['sometimes', 'integer', 'min:1'],
            'service_type' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(ServiceLifecycleService::STATUSES)],
            'expires_in_days_from' => ['sometimes', 'integer'],
            'expires_in_days_to' => ['sometimes', 'integer', 'gte:expires_in_days_from'],
            'category' => ['sometimes', Rule::in(['expiring_soon', 'expired', 'suspended', 'not_renewed'])],
        ];
    }
}
