<?php

namespace App\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'location_id' => $this->location_id,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'expires_at' => $this->expires_at?->toDateString(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'status' => $this->status,
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->whenLoaded('uploader'),
            'location' => $this->whenLoaded('location'),
            'replaces_document_id' => $this->replaces_document_id,
            'versions' => $this->whenLoaded('versions'),
            'scanned_at' => $this->scanned_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
