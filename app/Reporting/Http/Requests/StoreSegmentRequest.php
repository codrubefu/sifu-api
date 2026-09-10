<?php

namespace App\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSegmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'criteria' => ['required', 'array'],
            'criteria.expires_in_days' => ['sometimes', 'integer', 'min:0', 'max:3650'],
            'criteria.expired' => ['sometimes', 'boolean'], 'criteria.active' => ['sometimes', 'boolean'],
            'criteria.location_id' => ['sometimes', 'integer'],
            'criteria.service_type' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
