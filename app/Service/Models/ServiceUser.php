<?php

namespace App\Service\Models;

use App\Payments\Models\Payment;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'service_id', 'user_id', 'invoice_number', 'bill_number', 'status', 'start_date', 'expires_at',
    'accesses_used', 'activated_at', 'suspended_at', 'resume_at',
    'status_reason', 'activation_payment_id',
])]
class ServiceUser extends Pivot
{
    use LogsModelChanges;

    protected $table = 'service_user';

    public $incrementing = true;

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activationPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'activation_payment_id');
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'expires_at' => 'datetime',
            'accesses_used' => 'integer',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'resume_at' => 'datetime',
        ];
    }
}
