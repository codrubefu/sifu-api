<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
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

    public function test_user_with_manage_right_can_create_right(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['rights.manage']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rights', [
                'name' => 'reports.view',
                'label' => 'View reports',
                'description' => 'Read report data.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'reports.view');

        $this->assertDatabaseHas('rights', ['name' => 'reports.view']);
    }

    public function test_user_with_manage_right_can_update_right(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['rights.manage']);
        $right = Right::query()->create([
            'name' => 'reports.view',
            'label' => 'View reports',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/rights/{$right->id}", [
                'label' => 'Updated reports',
            ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Updated reports');
    }

    public function test_user_with_manage_right_can_delete_unassigned_right(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['rights.manage']);
        $right = Right::query()->create([
            'name' => 'reports.view',
            'label' => 'View reports',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/rights/{$right->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('rights', ['id' => $right->id]);
    }

    public function test_assigned_right_cannot_be_deleted(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['rights.manage']);
        $right = Right::query()->create([
            'name' => 'reports.view',
            'label' => 'View reports',
        ]);
        $group = Group::query()->create([
            'name' => 'manager',
            'label' => 'Manager',
        ]);
        $group->rights()->attach($right);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/rights/{$right->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a right assigned to groups.');
    }

    public function test_user_without_manage_right_cannot_create_right(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['rights.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/rights', [
                'name' => 'blocked.view',
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
