<?php

namespace App\Users\Models;

use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'user_id',
    'location_id',
    'uploaded_by',
    'replaces_document_id',
    'category',
    'title',
    'description',
    'expires_at',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'extension',
    'size',
    'checksum',
    'status',
    'scanned_at',
])]
class UserDocument extends Model
{
    use BelongsToAuthenticatedOrganization;
    use HasFactory;
    use LogsModelChanges;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REPLACED = 'replaced';
    public const STATUS_DELETED = 'deleted';

    public const CATEGORIES = [
        'membership_request',
        'identity_document',
        'gdpr_agreement',
        'certificate',
        'contract',
        'photo',
        'other',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function replacedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_document_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_document_id');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'scanned_at' => 'datetime',
            'size' => 'integer',
        ];
    }
}
