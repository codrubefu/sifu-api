<?php

namespace App\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RightResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'groups_count' => $this->whenCounted('groups'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
