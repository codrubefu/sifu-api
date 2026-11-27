<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Service\Models\ServiceUser;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use App\Users\Services\BearerTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security regression (F-01): `service_user` has no tenant scope, so `{assignment}` routes must never
 * resolve another organization's assignment.
 */
class ServiceAssignmentTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_actions_on_another_organizations_assignment_return_404_and_change_nothing(): void
    {
        $token = $this->adminTokenFor(Organization::factory()->create());
        $foreign = $this->assignment(Organization::factory()->create(), ['price' => 0, 'max_accesses' => 10], [
            'status' => 'active',
            'start_date' => now()->subDay(),
            'activated_at' => now()->subDay(),
            'accesses_used' => 2,
        ]);

        $client = $this->withToken($token);
        $client->postJson("/api/service-assignments/{$foreign->id}/suspend", ['reason' => 'x'])->assertNotFound();
        $client->postJson("/api/service-assignments/{$foreign->id}/consume")->assertNotFound();
        $client->postJson("/api/service-assignments/{$foreign->id}/activate")->assertNotFound();
        $client->postJson("/api/service-assignments/{$foreign->id}/invoice")->assertNotFound();
        $client->getJson("/api/service-assignments/{$foreign->id}/invoice/pdf")->assertNotFound();
        $client->getJson("/api/service-assignments/{$foreign->id}/payment-note")->assertNotFound();

        $foreign->refresh();
        $this->assertSame('active', $foreign->status);
        $this->assertSame(2, $foreign->accesses_used);
        $this->assertNull($foreign->invoice_number);
        $this->assertNull($foreign->suspended_at);
    }

    public function test_resume_and_activate_on_another_organizations_assignment_return_404(): void
    {
        $token = $this->adminTokenFor(Organization::factory()->create());
        $otherOrganization = Organization::factory()->create();
        $suspended = $this->assignment($otherOrganization, ['price' => 0], [
            'status' => 'suspended',
            'start_date' => now()->subDay(),
            'suspended_at' => now(),
            'status_reason' => 'Owner decision',
        ]);
        $pending = $this->assignment($otherOrganization, ['price' => 0], ['status' => 'pending']);

        $this->withToken($token)->postJson("/api/service-assignments/{$suspended->id}/resume")->assertNotFound();
        $this->withToken($token)->postJson("/api/service-assignments/{$pending->id}/activate")->assertNotFound();

        $this->assertSame('suspended', $suspended->refresh()->status);
        $this->assertSame('pending', $pending->refresh()->status);
    }

    public function test_shared_service_assignment_of_another_organizations_member_returns_404(): void
    {
        $token = $this->adminTokenFor(Organization::factory()->create());
        $foreign = $this->assignment(Organization::factory()->create(), ['price' => 0, 'organization_id' => null], [
            'status' => 'active',
            'start_date' => now()->subDay(),
        ]);

        $this->withToken($token)->postJson("/api/service-assignments/{$foreign->id}/suspend", ['reason' => 'x'])->assertNotFound();
        $this->withToken($token)->postJson("/api/service-assignments/{$foreign->id}/invoice")->assertNotFound();

        $this->assertSame('active', $foreign->refresh()->status);
        $this->assertNull($foreign->invoice_number);
    }

    public function test_own_organization_assignment_lifecycle_still_works(): void
    {
        $organization = Organization::factory()->create();
        $token = $this->adminTokenFor($organization);
        $own = $this->assignment($organization, ['price' => 0], ['status' => 'active', 'start_date' => now()->subDay()]);

        $this->withToken($token)->postJson("/api/service-assignments/{$own->id}/suspend", ['reason' => 'Medical'])->assertOk();
        $this->assertSame('suspended', $own->refresh()->status);

        $this->withToken($token)->postJson("/api/service-assignments/{$own->id}/resume")->assertOk();
        $this->assertSame('active', $own->refresh()->status);

        $this->withToken($token)->postJson("/api/service-assignments/{$own->id}/consume")->assertOk();
        $this->assertSame(1, $own->refresh()->accesses_used);
    }

    private function adminTokenFor(Organization $organization): string
    {
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $group = Group::query()->create([
            'name' => 'admins-'.$organization->id,
            'label' => 'Admins',
            'organization_id' => $organization->id,
        ]);
        foreach (['services.view', 'services.update', 'services.manage'] as $name) {
            $group->rights()->attach(Right::query()->firstOrCreate(['name' => $name], ['label' => $name])->id);
        }
        $admin->groups()->attach($group);

        return app(BearerTokenService::class)->create($admin);
    }

    private function assignment(Organization $organization, array $serviceOverrides = [], array $assignmentOverrides = []): ServiceUser
    {
        $member = User::factory()->create(['organization_id' => $organization->id]);
        $service = Service::query()->create(array_merge([
            'organization_id' => $organization->id,
            'name' => 'Pass',
            'price' => 10,
            'currency' => 'EUR',
            'duration_days' => 30,
            'expiration_rule' => 'duration',
            'grace_period_days' => 0,
            'is_active' => true,
        ], $serviceOverrides));

        return ServiceUser::query()->create(array_merge([
            'service_id' => $service->id,
            'user_id' => $member->id,
            'status' => 'pending',
        ], $assignmentOverrides));
    }
}
