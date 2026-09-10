<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_payments_with_related_details(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['payments.view']);
        Payment::query()->create($this->paymentData([
            'model_id' => 77,
            'admin_id' => $admin->id,
            'organization_id' => $admin->organization_id,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/payments')
            ->assertOk()
            ->assertJsonPath('data.0.payment_type', 'card')
            ->assertJsonPath('data.0.amount', '25.50')
            ->assertJsonPath('data.0.model_type', Payment::MODEL_TYPE_SERVICE_USER)
            ->assertJsonPath('data.0.model_id', 77)
            ->assertJsonPath('data.0.admin.id', $admin->id);
    }

    public function test_user_with_create_right_can_create_payment_for_authenticated_admin(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['payments.create']);
        $modelId = $this->serviceAssignment($admin);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/payments', [
                'first_name' => 'Jane',
                'last_name' => 'Client',
                'payment_type_id' => Payment::TYPE_CASH,
                'model_id' => $modelId,
                'amount' => 99.99,
                'paid_at' => '2026-06-01 10:15:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.model_type', Payment::MODEL_TYPE_SERVICE_USER)
            ->assertJsonPath('data.payment_type', 'cash')
            ->assertJsonPath('data.model_id', $modelId)
            ->assertJsonPath('data.admin_id', $admin->id);

        $this->assertDatabaseHas('payments', [
            'first_name' => 'Jane',
            'last_name' => 'Client',
            'payment_type_id' => Payment::TYPE_CASH,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $modelId,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_create_payment_validates_required_supported_fields(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['payments.create']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/payments', [
                'payment_type_id' => 9,
                'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
                'amount' => -1,
                'paid_at' => 'not-a-date',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'payment_type_id',
                'model_id',
                'amount',
                'paid_at',
            ]);
    }

    public function test_user_with_update_right_can_attach_service_model_to_payment(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['payments.update']);
        $modelId = $this->serviceAssignment($admin);
        $payment = Payment::query()->create($this->paymentData([
            'model_id' => 10,
            'admin_id' => $admin->id,
            'organization_id' => $admin->organization_id,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/payments/{$payment->id}/attach-model", [
                'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
                'model_id' => $modelId,
            ])
            ->assertOk()
            ->assertJsonPath('data.model_type', Payment::MODEL_TYPE_SERVICE_USER)
            ->assertJsonPath('data.model_id', $modelId);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $modelId,
        ]);
    }

    public function test_user_with_create_right_can_create_payment_for_event_occurrence_participant(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['payments.create']);
        $modelId = $this->eventAssignment($admin);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/payments', [
                'first_name' => 'Jane',
                'last_name' => 'Client',
                'payment_type_id' => Payment::TYPE_CARD,
                'model_type' => Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER,
                'model_id' => $modelId,
                'amount' => 49.99,
                'paid_at' => '2026-06-01 10:15:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.model_type', Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER)
            ->assertJsonPath('data.payment_type', 'card')
            ->assertJsonPath('data.model_id', $modelId)
            ->assertJsonPath('data.admin_id', $admin->id);

        $this->assertDatabaseHas('payments', [
            'first_name' => 'Jane',
            'last_name' => 'Client',
            'payment_type_id' => Payment::TYPE_CARD,
            'model_type' => Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER,
            'model_id' => $modelId,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_user_without_payment_right_cannot_create_payment(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['payments.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/payments', $this->paymentData([
                'model_id' => 30,
            ]))
            ->assertForbidden();
    }

    private function paymentData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'John',
            'last_name' => 'Member',
            'payment_type_id' => Payment::TYPE_CARD,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => null,
            'amount' => 25.50,
            'paid_at' => '2026-06-01 12:00:00',
            'admin_id' => null,
        ], $overrides);
    }

    private function authenticatedUserWithRights(array $rightNames): array
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Test Group',
        ]);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->create([
                'name' => $rightName,
                'label' => $rightName,
            ]);
            $group->rights()->attach($right);
        }

        $user->groups()->attach($group);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$user, $token];
    }

    private function serviceAssignment(User $operator): int
    {
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);
        $serviceId = DB::table('services')->insertGetId([
            'organization_id' => $operator->organization_id,
            'name' => 'Membership', 'description' => 'Test', 'price' => 100, 'currency' => 'RON',
            'duration_days' => 365, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('service_user')->insertGetId([
            'service_id' => $serviceId, 'user_id' => $member->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function eventAssignment(User $operator): int
    {
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);
        $eventId = DB::table('events')->insertGetId([
            'organization_id' => $operator->organization_id, 'title' => 'Event', 'start_time' => '10:00:00',
            'end_time' => '11:00:00', 'recurrence_type' => 'once', 'start_date' => now()->toDateString(),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $occurrenceId = DB::table('event_occurrences')->insertGetId([
            'organization_id' => $operator->organization_id, 'event_id' => $eventId,
            'occurrence_date' => now()->toDateString(), 'start_datetime' => now(), 'end_datetime' => now()->addHour(),
            'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('event_occurrence_user')->insertGetId([
            'event_occurrence_id' => $occurrenceId, 'user_id' => $member->id,
            'status' => 'registered', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
