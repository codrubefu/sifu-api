<?php

namespace App\Sms\Models;

use App\Service\Models\Service;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'service_id',
    'service_user_id',
    'type',
    'destination',
    'message',
    'status',
    'sent_at',
])]
class SmsMessage extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const TYPE_SERVICE_EXPIRING = 'service_expiring';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
