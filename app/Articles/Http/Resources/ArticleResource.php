<?php

namespace App\Articles\Http\Resources;

use App\Users\Http\Resources\GroupResource;
use App\Users\Http\Resources\LocationResource;
use App\Users\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'publish_at' => $this->publish_at,
            'expires_at' => $this->expires_at,
            'priority' => $this->priority,
            'status' => $this->status,
            'audience_segment' => $this->audience_segment,
            'segment_id' => $this->segment_id,
            'created_by' => $this->created_by,
            'author' => UserResource::make($this->whenLoaded('author')),
            'groups' => GroupResource::collection($this->whenLoaded('groups')),
            'locations' => LocationResource::collection($this->whenLoaded('locations')),
            'delivered_at' => $this->whenLoaded('receipts', fn () => $this->receipts->first()?->delivered_at),
            'viewed_at' => $this->whenLoaded('receipts', fn () => $this->receipts->first()?->viewed_at),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
