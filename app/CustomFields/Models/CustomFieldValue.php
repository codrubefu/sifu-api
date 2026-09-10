<?php

namespace App\CustomFields\Models;

use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use App\Users\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'custom_field_id',
    'entity_type',
    'entity_id',
    'value_text',
    'value_number',
    'value_date',
    'value_json',
])]
class CustomFieldValue extends Model
{
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'value_number' => 'decimal:6',
            'value_date' => 'datetime',
            'value_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}
