<?php

namespace App\CustomFields\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'entity_type' => $this->entity_type,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'options' => $this->options,
            'validation_rules' => $this->validation_rules,
            'is_required' => $this->is_required,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
