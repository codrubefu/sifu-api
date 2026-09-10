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

    public function activate(ServiceUser $assignment, ?Payment $payment = null, ?CarbonInterface $at = null): ServiceUser
    {
        return DB::transaction(function () use ($assignment, $payment, $at): ServiceUser {
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
            DB::afterCommit(function () use ($assignmentId, $actorId, $oldValues): void {
                $activatedAssignment = ServiceUser::query()->with(['service', 'user'])->find($assignmentId);
                if ($activatedAssignment === null) {
                    return;
                }

                NotificationRequested::dispatch(
                    $activatedAssignment->user,
                    NotificationRequested::SERVICE_ACTIVATED,
                    "service.activated:{$activatedAssignment->id}:{$activatedAssignment->activated_at?->timestamp}",
                    ['service' => $activatedAssignment->service->name],
                );

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
            } elseif ($assignment->service->max_accesses !== null && $assignment->accesses_used >= $assignment->service->max_accesses) {
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
            } elseif ($assignment->service->max_accesses !== null && $assignment->accesses_used >= $assignment->service->max_accesses) {
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

    public function consumeEventAccess(User $user, Event $event, ?CarbonInterface $at = null): ?ServiceUser
    {
        $serviceId = $event->required_service_id;
        if ($serviceId === null) {
            return null;
        }

        $assignment = ServiceUser::query()
            ->where('user_id', $user->id)
            ->where('service_id', $serviceId)
            ->whereHas('service', fn ($query) => $query->where('max_accesses', '>', 0))
            ->latest('id')
            ->first();

        if ($assignment === null) {
            throw ValidationException::withMessages(['service' => 'The required service assignment was not found.']);
        }

        return $this->consumeAccess($assignment, $at);
    }

    private function isExpired(ServiceUser $assignment, CarbonInterface $at): bool
    {
        if ($assignment->expires_at === null) {
            return false;
        }

        return $at->isAfter($assignment->expires_at->copy()->addDays($assignment->service->grace_period_days));
    }

    private function locked(ServiceUser $assignment): ServiceUser
    {
        return ServiceUser::query()->with('service')->lockForUpdate()->findOrFail($assignment->id);
    }
}
