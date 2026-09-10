<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Payments\Models\Payment;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_and_view_services(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['services.view']);

        $service = Service::query()->create($this->serviceData([
            'name' => 'Basic',
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/services')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Basic']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Basic');
    }

    public function test_user_with_create_right_can_create_service(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['services.create']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/services', $this->serviceData([
                'name' => 'Pro',
                'price' => 49.99,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pro')
            ->assertJsonPath('data.currency', 'EUR');

        $this->assertDatabaseHas('services', [
            'name' => 'Pro',
            'price' => 49.99,
        ]);
    }

    public function test_user_with_update_right_can_update_and_toggle_service(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['services.update']);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Starter',
            'is_active' => true,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/services/{$service->id}", [
                'price' => 19.99,
            ])
            ->assertOk()
            ->assertJsonPath('data.price', '19.99');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/services/{$service->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_user_with_delete_and_restore_rights_can_delete_and_restore_service(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['services.delete', 'services.restore']);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Legacy',
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/services/{$service->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('services', ['id' => $service->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/services/{$service->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Legacy')
            ->assertJsonPath('data.deleted_at', null);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_service_is_blocked_when_service_user_delete_setting_is_false_and_users_are_assigned(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['services.delete']);
        config()->set("organization-access.settings.{$admin->organization_id}.delete_service.service_user", false);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Assigned service',
        ]));
        $service->users()->attach($member, [
            'status' => 'active',
            'start_date' => '2026-08-01',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/services/{$service->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete Service because it still has related Users.');

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('service_user', [
            'service_id' => $service->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_manage_right_allows_all_service_actions(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['services.manage']);
        $service = Service::query()->create($this->serviceData());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/services')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/services/{$service->id}", [
                'name' => 'Managed',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Managed');
    }

    public function test_user_without_create_right_cannot_create_service(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['services.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/services', $this->serviceData())
            ->assertForbidden();
    }

    public function test_service_validation_requires_valid_payload(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['services.create']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/services', [
                'name' => '',
                'price' => -1,
                'currency' => 'EURO',
                'duration_days' => 0,
                'max_users' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'price',
                'currency',
                'duration_days',
                'max_users',
            ]);
    }

    public function test_updating_service_user_ids_preserves_existing_assignment_payment_link(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['services.update']);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Preserve pivot',
            'price' => 100,
        ]));

        $service->users()->attach($member, [
            'status' => 'active',
            'start_date' => '2026-08-01',
            'activated_at' => '2026-08-01 10:00:00',
        ]);
        $assignmentId = $service->users()->whereKey($member->id)->first()->pivot->id;
        $payment = Payment::query()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Ana',
            'last_name' => 'Pop',
            'payment_type_id' => Payment::TYPE_CARD,
            'status' => Payment::STATUS_CONFIRMED,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => $assignmentId,
            'amount' => 100,
            'paid_at' => '2026-08-01 10:00:00',
            'admin_id' => $admin->id,
        ]);
        DB::table('service_user')->where('id', $assignmentId)->update(['activation_payment_id' => $payment->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/services/{$service->id}", $this->serviceData([
                'name' => 'Preserved pivot',
                'user_ids' => [$member->id],
            ]))
            ->assertOk();

        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'service_id' => $service->id,
            'user_id' => $member->id,
            'status' => 'active',
            'activation_payment_id' => $payment->id,
        ]);
    }

    public function test_generate_invoice_assigns_invoice_number_once(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['services.update']);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData([
            'name' => 'Manual invoice',
        ]));

        $service->users()->attach($member, [
            'bill_number' => 'BILL000001',
            'status' => 'active',
            'start_date' => '2026-08-22',
        ]);
        $assignmentId = $service->users()->whereKey($member->id)->first()->pivot->id;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/service-assignments/{$assignmentId}/invoice")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV000001');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/service-assignments/{$assignmentId}/invoice")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV000001');

        $this->assertDatabaseHas('service_user', [
            'id' => $assignmentId,
            'invoice_number' => 'INV000001',
            'bill_number' => 'BILL000001',
        ]);
        $this->assertDatabaseHas('organizations', [
            'id' => $admin->organization_id,
            'invoice_number' => 1,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/service-assignments/{$assignmentId}/invoice/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get("/api/service-assignments/{$assignmentId}/invoice/xml")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    private function serviceData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Enterprise',
            'description' => 'Enterprise service',
            'price' => 99.99,
            'currency' => 'EUR',
            'duration_days' => null,
            'max_users' => 25,
            'is_active' => true,
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
}
