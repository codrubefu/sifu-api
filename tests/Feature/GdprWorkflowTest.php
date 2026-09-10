<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Users\Jobs\GeneratePersonalDataExport;
use App\Users\Models\AuditLog;
use App\Users\Models\ConsentRecord;
use App\Users\Models\GdprExport;
use App\Users\Models\GdprRequest;
use App\Users\Models\User;
use App\Users\Services\GdprErasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GdprWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_is_tenant_safe_and_excludes_provider_payloads(): void
    {
        Storage::fake('local');
        $subject = User::factory()->create();
        $otherTenantUser = User::factory()->create();
        $request = GdprRequest::query()->create([
            'organization_id' => $subject->organization_id, 'user_id' => $subject->id,
            'type' => 'export', 'status' => 'processing', 'requested_by' => $subject->id,
        ]);
        $export = GdprExport::query()->create([
            'organization_id' => $subject->organization_id, 'user_id' => $subject->id,
            'gdpr_request_id' => $request->id, 'status' => 'pending', 'disk' => 'local',
        ]);

        (new GeneratePersonalDataExport($export->id))->handle();
        $payload = Storage::disk('local')->get($export->fresh()->path);

        $this->assertStringContainsString($subject->email, $payload);
        $this->assertStringNotContainsString($otherTenantUser->email, $payload);
        $this->assertStringNotContainsString('provider_payload', $payload);
    }

    public function test_consent_withdrawal_is_an_append_only_event_and_becomes_effective(): void
    {
        $user = User::factory()->create();
        foreach ([true, false] as $granted) {
            ConsentRecord::query()->create([
                'organization_id' => $user->organization_id, 'user_id' => $user->id, 'purpose' => 'notifications',
                'channel' => 'mail', 'policy_version' => '2026-01', 'granted' => $granted,
                'occurred_at' => now()->addSecond($granted ? 0 : 1), 'source' => 'self_service', 'actor_id' => $user->id,
            ]);
        }

        $this->assertCount(2, $user->consentRecords);
        $this->assertFalse($user->consentsTo('mail'));
        $this->assertFalse($user->consentRecords()->first()->delete());
    }

    public function test_erasure_anonymizes_activity_and_retains_minimized_financial_record(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create(['organization_id' => $actor->organization_id]);
        $payment = Payment::query()->create([
            'organization_id' => $subject->organization_id, 'first_name' => $subject->first_name, 'last_name' => $subject->last_name,
            'payment_type_id' => Payment::TYPE_CASH, 'status' => Payment::STATUS_CONFIRMED, 'amount' => 50,
            'paid_at' => now(), 'admin_id' => $subject->id, 'provider_payload' => ['secret' => 'provider-secret'],
        ]);
        $log = AuditLog::query()->create([
            'organization_id' => $subject->organization_id, 'model_type' => User::class, 'model_id' => $subject->id,
            'subject_user_id' => $subject->id, 'changed_by' => $subject->id, 'event_type' => 'user.updated',
            'action' => 'updated', 'old_values' => ['email' => $subject->email], 'new_values' => ['email' => 'new@example.com'],
        ]);
        $request = GdprRequest::query()->create([
            'organization_id' => $subject->organization_id, 'user_id' => $subject->id,
            'type' => 'erasure', 'status' => 'pending', 'requested_by' => $subject->id,
        ]);

        app(GdprErasureService::class)->execute($request, $subject, $actor);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'first_name' => 'REDACTED', 'amount' => 50]);
        $this->assertNull($payment->fresh()->provider_payload);
        $this->assertNull($log->fresh()->subject_user_id);
        $this->assertNull($log->fresh()->old_values);
        $this->assertFalse($subject->fresh()->active);
        $this->assertNull($request->fresh()->user_id);
        $this->assertNotEmpty($request->fresh()->execution_proof);
    }
}
