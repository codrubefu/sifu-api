<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowOrganizationSubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['month' => ['sometimes', 'date_format:Y-m'], 'organization_id' => ['prohibited']];
    }
}
