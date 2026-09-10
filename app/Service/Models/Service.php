<?php

namespace App\Service\Models;

use App\Payments\Models\Payment;
use App\Users\Models\Concerns\BelongsToAuthenticatedOrganization;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\Concerns\SetsOrganizationFromAuthenticatedUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Users\Models\Organization;
use App\Users\Models\User;

#[Fillable([
    'name',
    'description',
    'type',
    'price',
    'currency',
    'duration_days',
    'expiration_rule',
    'fixed_expires_at',
    'grace_period_days',
    'max_accesses',
    'max_users',
    'is_active',
    'organization_id',
])]
class Service extends Model
{
    use LogsModelChanges;
    use BelongsToAuthenticatedOrganization;
    use SetsOrganizationFromAuthenticatedUser;


    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    /** @use HasFactory<\Database\Factories\Factory<static>> */
    use HasFactory, SoftDeletes;

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ServiceUser::class)
            ->withPivot(['id', 'invoice_number', 'bill_number', 'status', 'start_date', 'expires_at', 'accesses_used', 'activated_at', 'suspended_at', 'resume_at', 'status_reason', 'activation_payment_id'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'fixed_expires_at' => 'datetime',
            'grace_period_days' => 'integer',
            'max_accesses' => 'integer',
            'max_users' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
