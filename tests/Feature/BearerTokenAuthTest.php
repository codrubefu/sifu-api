<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Service\Models\Service;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BearerTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_a_bearer_token(): void
    {
        $organization = Organization::query()->create(['name' => 'Login Org', 'slug' => 'login-org']);
        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'first_name', 'last_name', 'phone', 'active', 'email'],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_protected_routes_require_a_bearer_token(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->patchJson('/api/me/password')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->getJson('/api/me/events')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->getJson('/api/me/services')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_user_can_access_profile_with_bearer_token(): void
    {
        $organization = Organization::query()->create(['name' => 'Profile Org', 'slug' => 'profile-org']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'user@example.com');
    }

    public function test_user_can_list_children_across_location_scope(): void
    {
        $organization = Organization::query()->create(['name' => 'Children Org', 'slug' => 'children-org']);
        $parentLocation = Location::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Parent Location',
        ]);
        $childLocation = Location::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Child Location',
        ]);
        $parent = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'parent@example.com',
            'password' => 'password',
            'first_name' => 'Parent',
            'last_name' => 'User',
        ]);
        $child = User::factory()->create([
            'organization_id' => $organization->id,
            'parent_user_id' => $parent->id,
            'email' => null,
            'first_name' => 'Child',
            'last_name' => 'User',
        ]);

        $parent->locations()->attach($parentLocation->id);
        $child->locations()->attach($childLocation->id);

        $token = $this->postJson('/api/login', [
            'email' => 'parent@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me/children')
            ->assertOk()
            ->assertJsonPath('data.0.id', $child->id)
            ->assertJsonPath('data.0.parent_user_id', $parent->id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/me?child_id={$child->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $child->id);
    }

    public function test_user_can_update_own_password(): void
    {
        $organization = Organization::query()->create(['name' => 'Password Org', 'slug' => 'password-org']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'password@example.com',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'password@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/me/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password updated successfully.');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'organization_id' => $organization->id,
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_user_password_update_requires_current_password_and_confirmation(): void
    {
        $organization = Organization::query()->create(['name' => 'Password Validation Org', 'slug' => 'password-validation-org']);
        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'password-validation@example.com',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'password-validation@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/me/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password']);
    }

    public function test_user_can_list_own_services(): void
    {
        $organization = Organization::query()->create(['name' => 'Service Me Org', 'slug' => 'service-me-org']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'services@example.com',
            'password' => 'password',
        ]);
        $otherUser = User::factory()->create(['organization_id' => $organization->id]);
        $service = Service::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Own Service',
            'description' => 'Visible service',
            'price' => 49.99,
            'currency' => 'EUR',
            'duration_days' => 30,
            'max_users' => 10,
            'is_active' => true,
        ]);
        $otherService = Service::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Other Service',
            'description' => 'Hidden service',
            'price' => 99.99,
            'currency' => 'EUR',
            'duration_days' => 30,
            'max_users' => 10,
            'is_active' => true,
        ]);

        $user->services()->attach($service->id, [
            'start_date' => '2026-06-01',
            'expires_at' => '2026-07-01',
        ]);
        $otherUser->services()->attach($otherService->id, [
            'start_date' => '2026-06-01',
            'expires_at' => '2026-07-01',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'services@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me/services')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Own Service')
            ->assertJsonPath('data.0.start_date', '2026-06-01')
            ->assertJsonPath('data.0.expires_at', '2026-07-01')
            ->assertJsonMissing(['name' => 'Other Service']);
    }

    public function test_user_can_list_own_event_occurrences(): void
    {
        $organization = Organization::query()->create(['name' => 'Event Me Org', 'slug' => 'event-me-org']);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'events@example.com',
            'password' => 'password',
        ]);
        $otherUser = User::factory()->create(['organization_id' => $organization->id]);
        $event = Event::query()->create($this->eventData([
            'organization_id' => $organization->id,
            'title' => 'Own Event',
        ]));
        $otherEvent = Event::query()->create($this->eventData([
            'organization_id' => $organization->id,
            'title' => 'Other Event',
        ]));
        $occurrence = EventOccurrence::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'occurrence_date' => '2026-06-10',
            'start_datetime' => '2026-06-10 10:00:00',
            'end_datetime' => '2026-06-10 11:00:00',
            'status' => 'scheduled',
        ]);
        $otherOccurrence = EventOccurrence::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $otherEvent->id,
            'occurrence_date' => '2026-06-11',
            'start_datetime' => '2026-06-11 10:00:00',
            'end_datetime' => '2026-06-11 11:00:00',
            'status' => 'scheduled',
        ]);

        $occurrence->participants()->attach($user->id, [
            'status' => 'registered',
            'registered_at' => '2026-06-01 09:00:00',
            'notes' => 'Own note',
        ]);
        $otherOccurrence->participants()->attach($otherUser->id, [
            'status' => 'registered',
            'registered_at' => '2026-06-01 09:00:00',
            'notes' => 'Other note',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'events@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me/events')
            ->assertOk()
            ->assertJsonPath('data.0.id', $occurrence->id)
            ->assertJsonPath('data.0.event.title', 'Own Event')
            ->assertJsonPath('data.0.participant_status', 'registered')
            ->assertJsonPath('data.0.registered_at', '2026-06-01 09:00:00')
            ->assertJsonPath('data.0.notes', 'Own note')
            ->assertJsonMissing(['title' => 'Other Event']);
    }

    public function test_logout_revokes_the_current_bearer_token(): void
    {
        $organization = Organization::query()->create(['name' => 'Logout Org', 'slug' => 'logout-org']);
        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'organization_id' => $organization->id,
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_login_uses_the_selected_organization(): void
    {
        $firstOrganization = Organization::query()->create(['name' => 'First Login Org', 'slug' => 'first-login-org']);
        $secondOrganization = Organization::query()->create(['name' => 'Second Login Org', 'slug' => 'second-login-org']);
        User::factory()->create([
            'organization_id' => $firstOrganization->id,
            'email' => 'shared@example.com',
            'password' => 'first-password',
        ]);
        $secondUser = User::factory()->create([
            'organization_id' => $secondOrganization->id,
            'email' => 'shared@example.com',
            'password' => 'second-password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'shared@example.com',
            'organization_id' => $secondOrganization->id,
            'password' => 'second-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $secondUser->id);

        $this->postJson('/api/login', [
            'email' => 'shared@example.com',
            'organization_id' => $firstOrganization->id,
            'password' => 'second-password',
        ])
            ->assertUnauthorized();
    }

    private function eventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Event test',
            'description' => 'Event description',
            'location' => 'Room 1',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'recurrence_days' => null,
            'monthly_day' => null,
            'start_date' => '2026-06-10',
            'end_date' => null,
            'requires_active_service' => false,
            'required_service_id' => null,
            'requires_payment' => false,
            'payment_amount' => null,
            'payment_type' => null,
            'max_participants' => null,
            'status' => 'active',
        ], $overrides);
    }
}
