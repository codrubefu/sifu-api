<?php

namespace App\CustomFields\Models\Concerns;

use App\CustomFields\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCustomFieldValues
{
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', $this->customFieldEntityType());
    }

    public function customFieldEntityType(): string
    {
        return property_exists($this, 'customFieldEntityType')
            ? $this->customFieldEntityType
            : $this->getTable();
    }
}
