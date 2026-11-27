<?php

namespace App\Service\Models;

use App\Payments\Models\Payment;
use App\Users\Models\Concerns\LogsModelChanges;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Auth;

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

    /**
     * `service_user` has no organization column and no tenant global scope, so every lookup driven by an
     * authenticated request must be constrained explicitly: the member must belong to the actor's
     * organization and the service must be the organization's own or a shared (NULL) one.
     * Without an authenticated organization (jobs, payment callbacks, console) this is a no-op.
     */
    public function scopeVisibleToAuthenticatedOrganization(Builder $query): Builder
    {
        $actor = Auth::user();

        if (! $actor instanceof User || $actor->organization_id === null) {
            return $query;
        }

        $organizationId = (int) $actor->organization_id;

        return $query
            ->whereHas('user', fn (Builder $users) => $users->withoutGlobalScopes()
                ->where('users.organization_id', $organizationId))
            ->whereHas('service', fn (Builder $services) => $services->withoutGlobalScopes()
                ->where(fn (Builder $scoped) => $scoped->where('services.organization_id', $organizationId)
                    ->orWhereNull('services.organization_id')));
    }

    /** Route-model binding for `{assignment}`: another organization's assignment resolves to 404. */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQuery()
            ->visibleToAuthenticatedOrganization()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

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
