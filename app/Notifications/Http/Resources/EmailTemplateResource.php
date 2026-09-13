<?php

namespace App\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->only(['id', 'organization_id', 'type', 'subject', 'body', 'created_at', 'updated_at']);
    }
}
