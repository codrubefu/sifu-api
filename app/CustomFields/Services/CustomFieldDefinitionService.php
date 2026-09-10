<?php

namespace App\CustomFields\Services;

use App\CustomFields\Models\CustomField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CustomFieldDefinitionService
{
    public function forEntityType(int $organizationId, string $entityType): Collection
    {
        return Cache::remember(
            $this->cacheKey($organizationId, $entityType),
            now()->addMinutes(30),
            fn () => CustomField::query()
                ->where('organization_id', $organizationId)
                ->where('entity_type', $entityType)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    public function clearCache(int $organizationId, string $entityType): void
    {
        Cache::forget($this->cacheKey($organizationId, $entityType));
    }

    private function cacheKey(int $organizationId, string $entityType): string
    {
        return sprintf('custom_fields.%d.%s', $organizationId, $entityType);
    }
}
