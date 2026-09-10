<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_groups(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['groups.view']);

        Group::query()->create([
            'name' => 'staff',
            'label' => 'Staff',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/groups')
            ->assertOk()
            ->assertJsonFragment(['id' => $admin->groups()->first()->id])
            ->assertJsonFragment(['name' => 'staff']);
    }

    public function test_user_with_manage_right_can_create_group_with_rights(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['groups.manage']);
        $right = Right::query()->create([
            'name' => 'users.view',
            'label' => 'View users',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/groups', [
                'name' => 'manager',
                'label' => 'Manager',
                'description' => 'Can view operational data.',
                'right_ids' => [$right->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'manager')
            ->assertJsonPath('data.rights.0.id', $right->id);

        $this->assertDatabaseHas('groups', ['name' => 'manager']);
        $this->assertDatabaseHas('group_right', ['right_id' => $right->id]);
    }

    public function test_user_with_manage_right_can_update_group(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['groups.manage']);
        $group = Group::query()->create([
            'name' => 'staff',
            'label' => 'Staff',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/groups/{$group->id}", [
                'label' => 'Updated Staff',
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Updated Staff');
    }

    public function test_user_with_manage_right_can_delete_empty_group(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['groups.manage']);
        $group = Group::query()->create([
            'name' => 'temporary',
            'label' => 'Temporary',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/groups/{$group->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_group_with_users_cannot_be_deleted(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['groups.manage']);
        $group = Group::query()->create([
            'name' => 'staff',
            'label' => 'Staff',
        ]);
        $group->users()->attach(User::factory()->create());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/groups/{$group->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a group that still has users.');
    }

    public function test_user_without_manage_right_cannot_create_group(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['groups.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/groups', [
                'name' => 'blocked',
                'label' => 'Blocked',
            ])
            ->assertForbidden();
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
