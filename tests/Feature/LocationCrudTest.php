<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_view_right_can_list_locations(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);

        Location::query()->create([
            'name' => 'Main Office',
            'description' => 'Headquarters',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/locations')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Main Office']);
    }

    public function test_locations_are_listed_alphabetically_by_default(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);

        Location::query()->create(['name' => 'Warehouse']);
        Location::query()->create(['name' => 'Annex']);
        Location::query()->create(['name' => 'Main Office']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/locations')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Annex')
            ->assertJsonPath('data.1.name', 'Main Office')
            ->assertJsonPath('data.2.name', 'Warehouse');
    }

    public function test_user_with_manage_right_can_create_location_with_users(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['locations.manage']);
        $user = User::factory()->create(['organization_id' => $admin->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/locations', [
                'name' => 'Warehouse',
                'description' => 'Storage location',
                'user_ids' => [$user->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Warehouse')
            ->assertJsonPath('data.users.0.id', $user->id);

        $this->assertDatabaseHas('locations', ['name' => 'Warehouse']);
        $this->assertDatabaseHas('location_user', ['user_id' => $user->id]);
    }

    public function test_user_with_manage_right_can_update_location(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.manage']);
        $location = Location::query()->create([
            'name' => 'Warehouse',
            'description' => 'Storage location',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/locations/{$location->id}", [
                'description' => 'Updated storage location',
            ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated storage location');
    }

    public function test_user_with_manage_right_can_delete_location(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.manage']);
        $location = Location::query()->create([
            'name' => 'Warehouse',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/locations/{$location->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_user_without_manage_right_cannot_create_location(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['locations.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/locations', [
                'name' => 'Blocked',
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
