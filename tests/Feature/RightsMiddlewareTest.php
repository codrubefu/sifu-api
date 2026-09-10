<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RightsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_required_right_can_access_groups_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $right = Right::query()->create([
            'name' => 'groups.view',
            'label' => 'View groups',
        ]);
        $group = Group::query()->create([
            'name' => 'admin',
            'label' => 'Administrator',
        ]);
        $group->rights()->sync([$right->id]);
        $user->groups()->sync([$group->id]);

        $token = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/groups')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'admin');
    }

    public function test_user_without_required_right_is_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
        ]);
        $group = Group::query()->create([
            'name' => 'staff',
            'label' => 'Staff',
        ]);
        $user->groups()->sync([$group->id]);

        $token = $this->postJson('/api/login', [
            'email' => 'staff@example.com',
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/groups')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_user_without_any_explicit_right_has_profile_view_by_default(): void
    {
        Route::middleware(['auth.bearer', 'right:profile.view'])->get('/api/test-profile-right', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create([
            'email' => 'profile@example.com',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'profile@example.com',
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        $this->assertTrue($user->hasRight('profile.view'));
        $this->assertTrue($user->hasAnyRight(['profile.view']));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/test-profile-right')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_user_without_any_explicit_right_does_not_get_other_rights_by_default(): void
    {
        $user = User::factory()->create([
            'email' => 'no-rights@example.com',
            'password' => 'password',
        ]);

        $this->assertFalse($user->hasRight('groups.view'));
        $this->assertFalse($user->hasAnyRight(['groups.view']));
    }
}
