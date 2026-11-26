<?php

namespace App\Service\Services;

use App\Notifications\Events\NotificationRequested;
use App\Payments\Models\Payment;
use App\Service\Models\ServiceUser;
use App\Events\Models\Event;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceLifecycleService
{
    public const STATUSES = ['pending', 'active', 'expired', 'suspended', 'consumed', 'reserved'];

    public function __construct(private readonly BusinessActivityLogger $activityLogger) {}

    public function activate(ServiceUser $assignment, ?Payment $payment = null, ?CarbonInterface $at = null, bool $notify = true): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $payment, $at, $notify): ServiceUser {
            $assignment = $this->locked($assignment);
            $at ??= now();

            if (! in_array($assignment->status, ['pending', 'reserved', 'expired'], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending, reserved, or expired assignments can be activated.']);
            }

            $service = $assignment->service;

            if ($payment !== null) {
                if (
                    $payment->status !== Payment::STATUS_CONFIRMED
                    || (int) $payment->organization_id !== (int) $service->organization_id
                    || $payment->model_type !== Payment::MODEL_TYPE_SERVICE_USER
                    || (int) $payment->model_id !== (int) $assignment->id
                ) {
                    throw ValidationException::withMessages(['payment_id' => 'Payment must be confirmed and linked to this service assignment.']);
                }
            } elseif ((float) $service->price > 0) {
                throw ValidationException::withMessages(['payment_id' => 'Payment is required to activate a paid service assignment.']);
            }

            $start = $assignment->start_date ?? $at;
            $expiresAt = match ($service->expiration_rule) {
                'none' => null,
                'fixed_date' => $service->fixed_expires_at,
                default => $service->duration_days === null ? null : $start->copy()->addDays($service->duration_days),
            };
            $status = $start->isAfter($at) ? 'reserved' : 'active';

            $oldValues = $assignment->only(['status', 'start_date', 'expires_at', 'activated_at', 'activation_payment_id']);
            $assignment->forceFill([
                'status' => $status,
                'start_date' => $start,
                'expires_at' => $expiresAt,
                'activated_at' => $at,
                'activation_payment_id' => $payment?->id,
                'suspended_at' => null,
                'resume_at' => null,
                'status_reason' => null,
            ])->saveQuietly();

            $assignmentId = (int) $assignment->id;
            $actorId = $payment?->admin_id;
            DB::afterCommit(function () use ($assignmentId, $actorId, $oldValues, $notify): void {
                $activatedAssignment = ServiceUser::query()->with(['service', 'user'])->find($assignmentId);
                if ($activatedAssignment === null) {
                    return;
                }

                if ($notify) {
                    NotificationRequested::dispatch(
                        $activatedAssignment->user,
                        NotificationRequested::SERVICE_ACTIVATED,
                        "service.activated:{$activatedAssignment->id}:{$activatedAssignment->activated_at?->timestamp}",
                        ['service' => $activatedAssignment->service->name],
                    );
                }

                $this->activityLogger->record(
                    AuditLog::SERVICE_ACTIVATED,
                    $activatedAssignment->user,
                    $activatedAssignment,
                    $oldValues,
                    $activatedAssignment->only(['status', 'start_date', 'expires_at', 'activated_at', 'activation_payment_id']),
                    $actorId === null ? null : User::query()->withoutGlobalScopes()->find($actorId),
                );
            });

            return $assignment->refresh();
        });
    }

    public function suspend(ServiceUser $assignment, string $reason, ?CarbonInterface $resumeAt = null): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $reason, $resumeAt): ServiceUser {
            $assignment = $this->locked($assignment);
            if (! in_array($assignment->status, ['active', 'reserved'], true)) {
                throw ValidationException::withMessages(['status' => 'Only active or reserved assignments can be suspended.']);
            }
            $assignment->update(['status' => 'suspended', 'suspended_at' => now(), 'resume_at' => $resumeAt, 'status_reason' => $reason]);

            return $assignment->refresh();
        });
    }

    public function resume(ServiceUser $assignment, ?CarbonInterface $at = null): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $at): ServiceUser {
            $assignment = $this->locked($assignment);
            $at ??= now();
            if ($assignment->status !== 'suspended') {
                throw ValidationException::withMessages(['status' => 'Only suspended assignments can be resumed.']);
            }
            if ($this->isExpired($assignment, $at)) {
                $status = 'expired';
            } elseif ($assignment->service?->max_accesses !== null && $assignment->accesses_used >= $assignment->service->max_accesses) {
                $status = 'consumed';
            } elseif ($assignment->start_date?->isAfter($at)) {
                $status = 'reserved';
            } else {
                $status = 'active';
            }
            $assignment->update(['status' => $status, 'suspended_at' => null, 'resume_at' => null, 'status_reason' => null]);

            return $assignment->refresh();
        });
    }

    public function refresh(ServiceUser $assignment, ?CarbonInterface $at = null): ServiceUser
    {
        $at ??= now();
        if ($assignment->status === 'suspended' && $assignment->resume_at?->lte($at)) {
            return $this->resume($assignment, $at);
        }
        if (in_array($assignment->status, ['active', 'reserved'], true)) {
            $status = $assignment->start_date?->isAfter($at) ? 'reserved' : 'active';
            if ($this->isExpired($assignment, $at)) {
                $status = 'expired';
            } elseif ($assignment->service?->max_accesses !== null && $assignment->accesses_used >= $assignment->service->max_accesses) {
                $status = 'consumed';
            }
            if ($status !== $assignment->status) {
                $assignment->update(['status' => $status]);
            }
        }

        return $assignment->refresh();
    }

    public function consumeAccess(ServiceUser $assignment, ?CarbonInterface $at = null): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $at): ServiceUser {
            $assignment = $this->refresh($this->locked($assignment), $at);
            if ($assignment->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'The service is not available for access.']);
            }
            $assignment->increment('accesses_used');

            return $this->refresh($assignment, $at);
        });
    }

    /**
     * Consumes one access per required service group of the event, atomically.
     *
     * For each group: among the user's active assignments for the group's services,
     * an unlimited one (`max_accesses` null/0) wins and nothing is consumed for that
     * group; otherwise one access is taken from the assignment expiring first
     * (`expires_at` asc, null last, then `id`). If any group has no usable assignment
     * the whole operation is rolled back and a ValidationException is thrown.
     * Events without `requires_active_service` or without groups consume nothing.
     *
     * @return array<int, ServiceUser> the consumed assignments, in group order
     */
    public function consumeEventAccess(User $user, Event $event, ?CarbonInterface $at = null): array
    {
        if (! $event->requires_active_service) {
            return [];
        }

        $event->loadMissing('serviceRequirements.service');
        $groups = $event->serviceGroupIds();
        if ($groups === []) {
            return [];
        }

        $at ??= now();

        return DB::transaction(function () use ($user, $groups, $at): array {
            $consumed = [];

            foreach ($groups as $index => $serviceIds) {
                $assignments = ServiceUser::query()
                    ->with('service')
                    ->where('user_id', $user->id)
                    ->whereIn('service_id', $serviceIds)
                    ->whereIn('status', ['active', 'reserved'])
                    ->get()
                    ->map(fn (ServiceUser $assignment): ServiceUser => $this->refresh($assignment, $at))
                    ->filter(fn (ServiceUser $assignment): bool => $assignment->status === 'active' && (bool) $assignment->service?->is_active);

                if ($assignments->contains(fn (ServiceUser $assignment): bool => in_array($assignment->service->max_accesses, [null, 0], true))) {
                    continue;
                }

                $assignment = $assignments
                    ->sort(function (ServiceUser $left, ServiceUser $right): int {
                        if ($left->expires_at === null || $right->expires_at === null) {
                            return [$left->expires_at === null, $left->id] <=> [$right->expires_at === null, $right->id];
                        }

                        return [$left->expires_at->getTimestamp(), $left->id] <=> [$right->expires_at->getTimestamp(), $right->id];
                    })
                    ->first();

                if ($assignment === null) {
                    throw ValidationException::withMessages(['service' => "No available service access for required service group {$index}."]);
                }

                $consumed[] = $this->consumeAccess($assignment, $at);
            }

            return $consumed;
        });
    }

    /**
     * SQL counterpart of "refresh() then status === 'active'" (the definition used by
     * User::activeServices()), without writing anything.
     *
     * The query must expose the `services` and `service_user` tables, e.g. the existence
     * query of User::services() (`whereHas('services', ...)`). The service visibility part
     * of activeServices() (soft deletes, organization scope) comes from that relation;
     * `services.is_active` is enforced here.
     *
     * An assignment is effectively active at $at when:
     * - its stored status is `active` or `reserved`, or `suspended` with `resume_at <= $at`
     *   (refresh() would resume it);
     * - `start_date` is null or `<= $at` (otherwise refresh() keeps/turns it `reserved`);
     * - it is not expired: `expires_at` is null or `$at <= expires_at + grace_period_days`;
     * - it is not consumed: `max_accesses` is null or `accesses_used < max_accesses`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Query\Builder  $query
     */
    public function constrainToEffectivelyActive($query, ?CarbonInterface $at = null): void
    {
        $at = ($at ?? now())->toImmutable();
        $graceValues = DB::table('services')
            ->selectRaw('DISTINCT COALESCE(grace_period_days, 0) AS grace')
            ->pluck('grace')
            ->map(fn ($grace): int => (int) $grace)
            ->unique()
            ->values();

        $query->where('services.is_active', true)
            ->where(function ($query) use ($at): void {
                $query->whereIn('service_user.status', ['active', 'reserved'])
                    ->orWhere(function ($query) use ($at): void {
                        $query->where('service_user.status', 'suspended')
                            ->whereNotNull('service_user.resume_at')
                            ->where('service_user.resume_at', '<=', $at);
                    });
            })
            ->where(function ($query) use ($at): void {
                $query->whereNull('service_user.start_date')
                    ->orWhere('service_user.start_date', '<=', $at);
            })
            ->where(function ($query) use ($at, $graceValues): void {
                $query->whereNull('service_user.expires_at');
                // Grace is per service; one branch per distinct grace value keeps the date
                // arithmetic in PHP and the SQL portable (SQLite in tests, MySQL in prod).
                foreach ($graceValues as $grace) {
                    $query->orWhere(function ($query) use ($at, $grace): void {
                        $query->whereRaw('COALESCE(services.grace_period_days, 0) = ?', [$grace])
                            ->where('service_user.expires_at', '>=', $at->subDays($grace));
                    });
                }
            })
            ->where(function ($query): void {
                $query->whereNull('services.max_accesses')
                    ->orWhereColumn('service_user.accesses_used', '<', 'services.max_accesses');
            });
    }

    private function isExpired(ServiceUser $assignment, CarbonInterface $at): bool
    {
        if ($assignment->expires_at === null) {
            return false;
        }

        return $at->isAfter($assignment->expires_at->copy()->addDays((int) ($assignment->service?->grace_period_days ?? 0)));
    }

    private function locked(ServiceUser $assignment): ServiceUser
    {
        return ServiceUser::query()->with('service')->lockForUpdate()->findOrFail($assignment->id);
    }
}
