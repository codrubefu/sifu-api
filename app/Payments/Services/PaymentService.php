<?php

namespace App\Payments\Services;

use App\Payments\Models\Payment;
use App\Service\Models\ServiceUser;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Models\AuditLog;
use App\Users\Models\Organization;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        private readonly BusinessActivityLogger $activityLogger,
        private readonly ServiceLifecycleService $serviceLifecycle,
    ) {}

    public function create(array $data, User $admin): Payment
    {
        $data['model_type'] ??= Payment::MODEL_TYPE_SERVICE_USER;
        $this->ensureSupportedModelType($data['model_type']);
        $this->ensurePayableBelongsToOrganization($data['model_type'], (int) $data['model_id'], (int) $admin->organization_id);

        return DB::transaction(function () use ($data, $admin): Payment {
            $data['admin_id'] = $admin->id;
            $data['organization_id'] = $admin->organization_id;
            $data['location_id'] = $admin->locations()->withoutGlobalScopes()->value('locations.id');
            $data['external_reference'] ??= (string) Str::uuid();
            $data['provider'] ??= config('services.payments.provider');
            $data['status'] = $this->confirmsImmediately((int) $data['payment_type_id'])
                ? Payment::STATUS_CONFIRMED
                : Payment::STATUS_INITIATED;
            $data['confirmed_at'] = $data['status'] === Payment::STATUS_CONFIRMED ? now() : null;

            $payment = Payment::query()->create($data);

            if ($payment->status === Payment::STATUS_CONFIRMED) {
                $this->completeConfirmation($payment);
            }

            $payment = $payment->refresh();
            $subject = $this->subjectFor($payment);
            $this->activityLogger->record(AuditLog::PAYMENT_RECORDED, $subject, $payment, [], [
                'amount' => $payment->amount,
                'payment_type' => $payment->paymentTypeName(),
                'paid_at' => $payment->paid_at,
            ], $admin);

            return $payment;
        });
    }

    public function attachModel(Payment $payment, string $modelType, int $modelId): Payment
    {
        $this->ensureSupportedModelType($modelType);
        $this->ensurePayableBelongsToOrganization($modelType, $modelId, (int) $payment->organization_id);

        $payment->update(['model_type' => $modelType, 'model_id' => $modelId]);

        return $payment;
    }

    public function processCallback(array $payload): Payment
    {
        return DB::transaction(function () use ($payload): Payment {
            $payment = Payment::query()
                ->where('external_reference', $payload['external_reference'])
                ->lockForUpdate()
                ->firstOrFail();

            $status = $payload['status'];
            if ($payment->status === $status || ($this->isTerminal($payment->status) && ! ($payment->status === Payment::STATUS_CONFIRMED && $status === Payment::STATUS_REFUNDED))) {
                return $payment;
            }

            $updates = [
                'status' => $status,
                'provider_transaction_id' => $payload['transaction_id'] ?? $payment->provider_transaction_id,
                'provider_payload' => $payload,
            ];

            if ($status === Payment::STATUS_CONFIRMED) {
                $updates['confirmed_at'] = now();
            } elseif ($status === Payment::STATUS_FAILED) {
                $updates['failed_at'] = now();
                $updates['failure_reason'] = $payload['failure_reason'] ?? null;
            } elseif ($status === Payment::STATUS_REFUNDED) {
                $updates['refunded_at'] = now();
            } elseif ($status === Payment::STATUS_CANCELLED) {
                $updates['cancelled_at'] = now();
            }

            $payment->update($updates);
            if ($status === Payment::STATUS_CONFIRMED) {
                $this->completeConfirmation($payment);
            }

            return $payment->refresh();
        });
    }

    private function completeConfirmation(Payment $payment): void
    {
        if ($payment->receipt_number === null) {
            $organization = Organization::query()->lockForUpdate()->findOrFail($payment->organization_id);
            $nextNumber = (int) $organization->receipt_number + 1;

            $organization->forceFill(['receipt_number' => $nextNumber])->save();
            $payment->update(['receipt_number' => sprintf('%s%06d', $organization->receipt_code ?: 'CH', $nextNumber)]);
        }

        if ($payment->model_type !== Payment::MODEL_TYPE_SERVICE_USER) {
            return;
        }

        $assignment = ServiceUser::query()->findOrFail($payment->model_id);
        $this->serviceLifecycle->activate($assignment, $payment);
    }

    private function confirmsImmediately(int $paymentTypeId): bool
    {
        return in_array($paymentTypeId, [Payment::TYPE_CASH, Payment::TYPE_CARD], true);
    }

    private function ensurePayableBelongsToOrganization(string $type, int $id, int $organizationId): void
    {
        $matches = $type === Payment::MODEL_TYPE_SERVICE_USER
            ? DB::table('service_user')->join('services', 'services.id', '=', 'service_user.service_id')->where('service_user.id', $id)->where('services.organization_id', $organizationId)->exists()
            : DB::table('event_occurrence_user')->join('event_occurrences', 'event_occurrences.id', '=', 'event_occurrence_user.event_occurrence_id')->where('event_occurrence_user.id', $id)->where('event_occurrences.organization_id', $organizationId)->exists();

        if (! $matches) {
            throw ValidationException::withMessages(['model_id' => 'The payable object does not belong to the authenticated organization.']);
        }
    }

    private function isTerminal(string $status): bool
    {
        return in_array($status, [Payment::STATUS_CONFIRMED, Payment::STATUS_FAILED, Payment::STATUS_REFUNDED, Payment::STATUS_CANCELLED], true);
    }

    private function ensureSupportedModelType(string $modelType): void
    {
        if (! in_array($modelType, Payment::MODEL_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported payable model type.');
        }
    }

    private function subjectFor(Payment $payment): ?User
    {
        $pivotTable = $payment->model_type === Payment::MODEL_TYPE_SERVICE_USER
            ? 'service_user'
            : 'event_occurrence_user';
        $userId = DB::table($pivotTable)->where('id', $payment->model_id)->value('user_id');

        return $userId ? User::query()->withoutGlobalScopes()->find($userId) : null;
    }
}
