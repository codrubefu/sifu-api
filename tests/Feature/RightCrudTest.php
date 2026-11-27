<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Database\Seeders\ApplicationRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RightCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_rights(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['rights.view']);

        Right::query()->create([
            'name' => 'users.view',
            'label' => 'View users',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/rights')
            ->assertOk()
            ->assertJsonFragment(['name' => 'users.view']);
    }

    public function test_user_with_view_right_can_show_right(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['rights.view']);
        $right = Right::query()->create(['name' => 'reports.view', 'label' => 'View reports']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/rights/{$right->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'reports.view');
    }

    /**
     * Rights are platform-wide and matched by name. A tenant admin (even one still holding the legacy
     * `rights.manage` right) must not be able to create, rename or delete them, otherwise renaming
     * e.g. `reports.view` <-> `users.manage` would escalate privileges in every other organization.
     */
    public function test_rights_catalogue_cannot_be_written_through_the_api(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['rights.view', 'rights.manage', 'groups.manage', 'users.manage']);
        $usersManage = Right::query()->where('name', 'users.manage')->firstOrFail();
        $reportsView = Right::query()->create(['name' => 'reports.view', 'label' => 'View reports']);

        $otherOrganizationGroup = Group::query()->create([
            'name' => 'other-org-reporting',
            'label' => 'Other org reporting',
            'organization_id' => Organization::query()->create(['name' => 'Other club', 'slug' => 'other-club'])->id,
        ]);
        $otherOrganizationGroup->rights()->attach($reportsView);

        $client = $this->withHeader('Authorization', "Bearer {$token}");

        $client->postJson('/api/rights', ['name' => 'blocked.view', 'label' => 'Blocked'])->assertMethodNotAllowed();
        $client->patchJson("/api/rights/{$reportsView->id}", ['name' => 'tmp'])->assertMethodNotAllowed();
        $client->putJson("/api/rights/{$usersManage->id}", ['name' => 'reports.view', 'label' => 'x'])->assertMethodNotAllowed();
        $client->deleteJson("/api/rights/{$reportsView->id}")->assertMethodNotAllowed();

        $this->assertDatabaseMissing('rights', ['name' => 'blocked.view']);
        $this->assertDatabaseHas('rights', ['id' => $reportsView->id, 'name' => 'reports.view', 'label' => 'View reports']);
        $this->assertDatabaseHas('rights', ['id' => $usersManage->id, 'name' => 'users.manage']);
        $this->assertSame(['reports.view'], $otherOrganizationGroup->rights()->pluck('name')->all());
    }

    public function test_application_rights_do_not_include_rights_manage(): void
    {
        $this->assertNotContains('rights.manage', ApplicationRights::definitions()->pluck('name')->all());
    }

    public function test_revoke_migration_removes_rights_manage_from_every_group(): void
    {
        $rightsManage = Right::query()->create(['name' => 'rights.manage', 'label' => 'Manage rights']);
        $rightsView = Right::query()->create(['name' => 'rights.view', 'label' => 'View rights']);
        $group = Group::query()->create(['name' => 'legacy-admin', 'label' => 'Legacy admin']);
        $group->rights()->attach([$rightsManage->id, $rightsView->id]);

        $migration = require database_path('migrations/2026_11_27_000001_revoke_rights_manage_right.php');
        $migration->up();

        $this->assertDatabaseMissing('rights', ['name' => 'rights.manage']);
        $this->assertDatabaseMissing('group_right', ['right_id' => $rightsManage->id]);
        $this->assertSame(['rights.view'], $group->rights()->pluck('name')->all());
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
