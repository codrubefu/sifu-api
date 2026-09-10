<?php

namespace App\CustomFields\Http\Resources;

use App\CustomFields\Services\CustomFieldValueService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $field = $this->resource['field'];
        $value = $this->resource['value'];

        return [
            'custom_field' => new CustomFieldResource($field),
            'value' => app(CustomFieldValueService::class)->fieldValue($field, $value),
        ];
    }
}
