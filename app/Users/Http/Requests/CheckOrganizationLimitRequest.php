<?php

namespace App\Users\Http\Requests;

use App\Users\Services\OrganizationSubscriptionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckOrganizationLimitRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['resource' => ['required', Rule::in(OrganizationSubscriptionService::RESOURCES)],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'month' => ['required_if:resource,events', 'nullable', 'date_format:Y-m'],
            'organization_id' => ['prohibited']];
    }
}
