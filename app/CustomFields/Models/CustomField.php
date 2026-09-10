<?php

namespace App\CustomFields\Models;

use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use App\Users\Models\Organization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'entity_type',
    'name',
    'slug',
    'type',
    'options',
    'validation_rules',
    'is_required',
    'sort_order',
])]
class CustomField extends Model
{
    use BelongsToAuthenticatedOrganization;
    use LogsModelChanges;
    use SetsOrganizationFromAuthenticatedUser;

    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTI_SELECT = 'multi_select';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_FILE = 'file';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_NUMBER,
        self::TYPE_DATE,
        self::TYPE_DATETIME,
        self::TYPE_EMAIL,
        self::TYPE_PHONE,
        self::TYPE_SELECT,
        self::TYPE_MULTI_SELECT,
        self::TYPE_CHECKBOX,
        self::TYPE_BOOLEAN,
        self::TYPE_FILE,
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'validation_rules' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
