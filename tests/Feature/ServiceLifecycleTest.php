<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Service\Models\Service;
use App\Service\Models\ServiceUser;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_payment_activates_and_audits_an_assignment(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment();
        $payment = $this->payment($assignment);

        $assignment = app(ServiceLifecycleService::class)->activate($assignment, $payment);

        $this->assertSame('active', $assignment->status);
        $this->assertSame($payment->id, $assignment->activation_payment_id);
        $this->assertTrue($assignment->expires_at->equalTo(now()->addDays(30)));
        $this->assertFalse(AuditLog::query()->where('model_type', ServiceUser::class)->where('model_id', $assignment->id)->where('event_type', AuditLog::SERVICE_ACTIVATED)->exists());
    }

    public function test_free_service_can_be_activated_without_payment(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment(['price' => 0]);

        $assignment = app(ServiceLifecycleService::class)->activate($assignment);

        $this->assertSame('active', $assignment->status);
        $this->assertNull($assignment->activation_payment_id);
        $this->assertTrue($assignment->activated_at->equalTo(now()));
    }

    public function test_paid_service_still_requires_payment_to_activate(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ServiceLifecycleService::class)->activate($this->assignment(['price' => 10]));
    }

    public function test_grace_period_includes_exact_boundary_then_expires_after_it(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment(['duration_days' => 1, 'grace_period_days' => 2]);
        $service = app(ServiceLifecycleService::class);
        $assignment = $service->activate($assignment, $this->payment($assignment));

        $this->assertSame('active', $service->refresh($assignment, now()->addDays(3))->status);
        $this->assertSame('expired', $service->refresh($assignment, now()->addDays(3)->addSecond())->status);
    }

    public function test_lifecycle_can_refresh_assignment_when_service_is_soft_deleted(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment(['duration_days' => 1, 'grace_period_days' => 2]);
        $service = app(ServiceLifecycleService::class);
        $assignment = $service->activate($assignment, $this->payment($assignment));
        $assignment->service->delete();

        $this->assertSame('expired', $service->refresh($assignment, now()->addDays(3)->addSecond())->status);
    }

    public function test_access_limit_consumes_assignment_on_last_access(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment(['max_accesses' => 2]);
        $service = app(ServiceLifecycleService::class);
        $assignment = $service->activate($assignment, $this->payment($assignment));

        $this->assertSame('active', $service->consumeAccess($assignment)->status);
        $assignment = $service->consumeAccess($assignment->refresh());
        $this->assertSame('consumed', $assignment->status);
        $this->assertSame(2, $assignment->accesses_used);
    }

    public function test_scheduled_and_manual_resume_follow_lifecycle_rules(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');
        $assignment = $this->assignment();
        $service = app(ServiceLifecycleService::class);
        $assignment = $service->activate($assignment, $this->payment($assignment));
        $assignment = $service->suspend($assignment, 'Medical leave', now()->addHour());

        $this->assertSame('suspended', $service->refresh($assignment, now()->addMinutes(59))->status);
        $this->assertSame('active', $service->refresh($assignment, now()->addHour())->status);

        $assignment = $service->suspend($assignment->refresh(), 'Manual review');
        $this->assertSame('active', $service->resume($assignment)->status);
    }

    private function assignment(array $serviceOverrides = []): ServiceUser
    {
        $user = User::factory()->create();
        $service = Service::query()->create(array_merge([
            'organization_id' => $user->organization_id,
            'name' => 'Pass',
            'price' => 10,
            'currency' => 'EUR',
            'duration_days' => 30,
            'expiration_rule' => 'duration',
            'grace_period_days' => 0,
            'is_active' => true,
        ], $serviceOverrides));

        return ServiceUser::query()->create([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    private function payment(ServiceUser $assignment): Payment
    {
        return Payment::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pop',
            'payment_type_id' => Payment::TYPE_CARD,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $assignment->id,
            'amount' => 10,
            'paid_at' => now(),
            'status' => Payment::STATUS_CONFIRMED,
            'organization_id' => $assignment->service->organization_id,
            'admin_id' => $assignment->user_id,
        ]);
    }
}
