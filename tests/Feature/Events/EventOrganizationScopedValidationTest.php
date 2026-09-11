<?php

namespace Tests\Feature\Events;

use App\Events\Models\Event;
use App\Events\Models\EventCategory;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the interaction between BelongsToAuthenticatedOrganization (which treats
 * organization_id IS NULL rows as shared/global, visible to every organization)
 * and the exists validation rules used by Store/UpdateEventRequest for
 * category_id/location_id/instructor_id/group_id.
 */
class EventOrganizationScopedValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_an_event_with_shared_null_organization_location_group_category_and_instructor_succeeds(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $event = Event::query()->create($this->eventData($admin->organization_id, [
            'recurrence_type' => 'once',
            'start_date' => '2026-10-05',
        ]));

        $sharedLocation = Location::query()->create([
            'name' => 'Remote '.fake()->unique()->numerify('###'),
            'organization_id' => null,
        ]);
        $sharedGroup = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Grup global',
            'organization_id' => null,
        ]);
        $sharedCategory = EventCategory::query()->create([
            'name' => 'Categorie globala '.fake()->unique()->numerify('###'),
            'organization_id' => null,
        ]);
        $sharedInstructor = User::factory()->create(['organization_id' => null]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$event->id}", [
                'location_id' => $sharedLocation->id,
                'group_id' => $sharedGroup->id,
                'category_id' => $sharedCategory->id,
                'instructor_id' => $sharedInstructor->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'location_id' => $sharedLocation->id,
            'group_id' => $sharedGroup->id,
            'category_id' => $sharedCategory->id,
            'instructor_id' => $sharedInstructor->id,
        ]);
    }

    public function test_creating_an_event_with_shared_null_organization_location_and_group_succeeds(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $sharedLocation = Location::query()->create([
            'name' => 'Remote '.fake()->unique()->numerify('###'),
            'organization_id' => null,
        ]);
        $sharedGroup = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Grup global',
            'organization_id' => null,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'location_id' => $sharedLocation->id,
                'group_id' => $sharedGroup->id,
            ]))
            ->assertCreated();

        $response->assertJsonPath('data.location_id', $sharedLocation->id)
            ->assertJsonPath('data.group_id', $sharedGroup->id);
    }

    public function test_updating_event_with_location_group_or_instructor_from_another_non_null_organization_is_still_rejected(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $event = Event::query()->create($this->eventData($admin->organization_id, [
            'recurrence_type' => 'once',
            'start_date' => '2026-10-05',
        ]));

        $otherOrganization = Organization::factory()->create();
        $otherLocation = Location::query()->create([
            'name' => 'Sala Alta Organizatie '.fake()->unique()->numerify('###'),
            'organization_id' => $otherOrganization->id,
        ]);
        $otherGroup = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Grupa alta organizatie',
            'organization_id' => $otherOrganization->id,
        ]);
        $otherInstructor = User::factory()->create(['organization_id' => $otherOrganization->id]);
        $otherCategory = EventCategory::query()->create([
            'name' => 'Categorie alta organizatie '.fake()->unique()->numerify('###'),
            'organization_id' => $otherOrganization->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$event->id}", ['location_id' => $otherLocation->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$event->id}", ['group_id' => $otherGroup->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_id']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$event->id}", ['instructor_id' => $otherInstructor->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['instructor_id']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$event->id}", ['category_id' => $otherCategory->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    private function eventData(int $organizationId, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organizationId,
            'title' => 'Eveniment test',
            'description' => 'Descriere eveniment',
            'location' => 'Sala 1',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => '2026-10-05',
            'end_date' => null,
            'status' => 'active',
        ], $overrides);
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Eveniment test',
            'description' => 'Descriere eveniment',
            'location' => 'Sala 1',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => '2026-10-05',
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

    private function authenticatedUserWithRights(array $rightNames): array
    {
        Organization::factory()->create();

        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Test Group',
        ]);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->firstOrCreate(
                ['name' => $rightName],
                ['label' => $rightName],
            );
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
