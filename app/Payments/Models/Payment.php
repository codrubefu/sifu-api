<?php

namespace App\Payments\Models;

use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Location;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'first_name',
    'last_name',
    'payment_type_id',
    'organization_id',
    'location_id',
    'status',
    'external_reference',
    'receipt_number',
    'provider',
    'provider_transaction_id',
    'bank_reference',
    'reconciled_at',
    'provider_payload',
    'model_type',
    'model_id',
    'amount',
    'paid_at',
    'admin_id',
    'confirmed_at',
    'failed_at',
    'refunded_at',
    'cancelled_at',
    'failure_reason',
])]
class Payment extends Model
{
    use HasFactory;
    use LogsModelChanges;

    public const TYPE_CASH = 1;
    public const TYPE_CARD = 2;
    public const TYPE_BANK_TRANSFER = 3;

    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [
        self::STATUS_INITIATED,
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_FAILED,
        self::STATUS_REFUNDED,
        self::STATUS_CANCELLED,
    ];

    public const MODEL_TYPE_SERVICE_USER = 'service_user';
    public const MODEL_TYPE_EVENT_OCCURRENCE_USER = 'event_occurrence_user';

    public const PAYMENT_TYPES = [
        self::TYPE_CASH => 'cash',
        self::TYPE_CARD => 'card',
        self::TYPE_BANK_TRANSFER => 'bank_transfer',
    ];

    public const MODEL_TYPES = [
        self::MODEL_TYPE_SERVICE_USER,
        self::MODEL_TYPE_EVENT_OCCURRENCE_USER,
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function paymentTypeName(): ?string
    {
        return self::PAYMENT_TYPES[$this->payment_type_id] ?? null;
    }

    protected function casts(): array
    {
        return [
            'payment_type_id' => 'integer',
            'model_id' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'provider_payload' => 'array',
            'reconciled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
