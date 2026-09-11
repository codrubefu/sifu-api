<?php

namespace Tests\Feature\Events;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_once_event_generates_a_single_occurrence(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'recurrence_type' => 'once',
                'start_date' => '2026-10-05',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.occurrences_count', 1);

        $eventId = $response->json('data.id');

        $this->assertDatabaseCount('event_occurrences', 1);
        $this->assertDatabaseHas('event_occurrences', [
            'event_id' => $eventId,
            'occurrence_date' => '2026-10-05',
            'status' => 'scheduled',
        ]);
    }

    public function test_creating_a_weekly_event_generates_one_occurrence_per_matching_day(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'recurrence_type' => 'weekly',
                'recurrence_days' => ['monday', 'wednesday'],
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-19',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.occurrences_count', 5);

        $eventId = $response->json('data.id');

        $this->assertDatabaseCount('event_occurrences', 5);
        foreach (['2026-10-05', '2026-10-07', '2026-10-12', '2026-10-14', '2026-10-19'] as $date) {
            $this->assertDatabaseHas('event_occurrences', ['event_id' => $eventId, 'occurrence_date' => $date]);
        }
    }

    public function test_creating_a_monthly_event_generates_one_occurrence_per_month(): void
    {
        Carbon::setTestNow('2026-11-01 10:00:00');
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'recurrence_type' => 'monthly',
                'monthly_day' => 15,
                'start_date' => '2026-10-01',
                'end_date' => '2026-12-31',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.occurrences_count', 3);

        $eventId = $response->json('data.id');

        $this->assertDatabaseCount('event_occurrences', 3);
        foreach (['2026-10-15', '2026-11-15', '2026-12-15'] as $date) {
            $this->assertDatabaseHas('event_occurrences', ['event_id' => $eventId, 'occurrence_date' => $date]);
        }

        Carbon::setTestNow();
    }

    public function test_updating_recurrence_days_regenerates_open_future_occurrences_but_preserves_ones_with_participants_and_the_past(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);
        $participant = User::factory()->create(['organization_id' => $admin->organization_id]);

        $eventId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'recurrence_type' => 'weekly',
                'recurrence_days' => ['monday', 'wednesday'],
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-19',
            ]))
            ->assertCreated()
            ->json('data.id');

        $event = Event::query()->findOrFail($eventId);

        // Simulate a past occurrence that predates the recurrence window, to verify it is left untouched.
        $pastOccurrence = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => '2026-08-01',
            'start_datetime' => '2026-08-01 10:00:00',
            'end_datetime' => '2026-08-01 11:00:00',
            'status' => 'scheduled',
        ]);

        $occurrenceWithParticipant = EventOccurrence::query()
            ->where('event_id', $event->id)
            ->where('occurrence_date', '2026-10-12')
            ->firstOrFail();
        $participantPivotId = DB::table('event_occurrence_user')->insertGetId([
            'event_occurrence_id' => $occurrenceWithParticipant->id,
            'user_id' => $participant->id,
            'status' => 'registered',
            'registered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$eventId}", ['recurrence_days' => ['monday']])
            ->assertOk();

        // Future occurrences without participants that no longer match the recurrence (Wednesdays) are gone.
        $this->assertDatabaseMissing('event_occurrences', ['event_id' => $eventId, 'occurrence_date' => '2026-10-07']);
        $this->assertDatabaseMissing('event_occurrences', ['event_id' => $eventId, 'occurrence_date' => '2026-10-14']);

        // The future occurrence with a participant is preserved untouched (same id, still scheduled).
        $this->assertDatabaseHas('event_occurrences', [
            'id' => $occurrenceWithParticipant->id,
            'event_id' => $eventId,
            'occurrence_date' => '2026-10-12',
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('event_occurrence_user', [
            'id' => $participantPivotId,
            'event_occurrence_id' => $occurrenceWithParticipant->id,
            'user_id' => $participant->id,
        ]);

        // Future Mondays still matching the new recurrence exist (regenerated where missing).
        $this->assertDatabaseHas('event_occurrences', ['event_id' => $eventId, 'occurrence_date' => '2026-10-05']);
        $this->assertDatabaseHas('event_occurrences', ['event_id' => $eventId, 'occurrence_date' => '2026-10-19']);

        // The past occurrence is untouched by regeneration.
        $this->assertDatabaseHas('event_occurrences', [
            'id' => $pastOccurrence->id,
            'occurrence_date' => '2026-08-01',
            'status' => 'scheduled',
        ]);
    }

    public function test_deleting_event_deletes_open_future_occurrences_cancels_ones_with_participants_and_soft_deletes_the_event(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);
        $participant = User::factory()->create(['organization_id' => $admin->organization_id]);

        $eventId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'recurrence_type' => 'weekly',
                'recurrence_days' => ['monday', 'wednesday'],
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-19',
            ]))
            ->assertCreated()
            ->json('data.id');

        $event = Event::query()->findOrFail($eventId);

        $pastOccurrence = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => '2026-08-01',
            'start_datetime' => '2026-08-01 10:00:00',
            'end_datetime' => '2026-08-01 11:00:00',
            'status' => 'scheduled',
        ]);

        $occurrenceWithParticipant = EventOccurrence::query()
            ->where('event_id', $event->id)
            ->where('occurrence_date', '2026-10-12')
            ->firstOrFail();
        $occurrenceWithParticipant->participants()->attach($participant->id, [
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/events/{$eventId}")
            ->assertOk();

        foreach (['2026-10-05', '2026-10-07', '2026-10-14', '2026-10-19'] as $date) {
            $this->assertDatabaseMissing('event_occurrences', ['event_id' => $eventId, 'occurrence_date' => $date]);
        }

        $this->assertDatabaseHas('event_occurrences', [
            'id' => $occurrenceWithParticipant->id,
            'occurrence_date' => '2026-10-12',
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('event_occurrences', [
            'id' => $pastOccurrence->id,
            'occurrence_date' => '2026-08-01',
            'status' => 'scheduled',
        ]);

        $this->assertSoftDeleted('events', ['id' => $eventId]);
    }

    public function test_event_exposes_resolved_location_instructor_and_group_when_set(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $location = Location::query()->create([
            'name' => 'Sala Centrala '.fake()->unique()->numerify('###'),
            'organization_id' => $admin->organization_id,
        ]);
        $instructor = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Ion',
            'last_name' => 'Popescu',
        ]);
        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Grupa avansati',
            'organization_id' => $admin->organization_id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'location_id' => $location->id,
                'instructor_id' => $instructor->id,
                'group_id' => $group->id,
            ]))
            ->assertCreated();

        $response->assertJsonPath('data.location_id', $location->id)
            ->assertJsonPath('data.location.id', $location->id)
            ->assertJsonPath('data.location.name', $location->name)
            ->assertJsonPath('data.instructor_id', $instructor->id)
            ->assertJsonPath('data.instructor.id', $instructor->id)
            ->assertJsonPath('data.instructor.name', 'Ion Popescu')
            ->assertJsonPath('data.group_id', $group->id)
            ->assertJsonPath('data.group.id', $group->id)
            ->assertJsonPath('data.group.name', $group->name);

        $eventId = $response->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/events/{$eventId}")
            ->assertOk()
            ->assertJsonPath('data.location.name', $location->name)
            ->assertJsonPath('data.instructor.name', 'Ion Popescu')
            ->assertJsonPath('data.group.name', $group->name);

        $listed = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/events')
            ->assertOk()
            ->json('data');
        $listedEvent = collect($listed)->firstOrFail(fn (array $event) => $event['id'] === $eventId);

        $this->assertSame($location->name, $listedEvent['location']['name']);
        $this->assertSame('Ion Popescu', $listedEvent['instructor']['name']);
        $this->assertSame($group->name, $listedEvent['group']['name']);
    }

    public function test_event_without_optional_dimensions_returns_null_location_instructor_and_group(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload())
            ->assertCreated();

        $response->assertJsonPath('data.location_id', null)
            ->assertJsonPath('data.location', null)
            ->assertJsonPath('data.instructor_id', null)
            ->assertJsonPath('data.instructor', null)
            ->assertJsonPath('data.group_id', null)
            ->assertJsonPath('data.group', null);
    }

    public function test_setting_location_instructor_or_group_from_another_organization_is_rejected(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $otherOrganization = Organization::factory()->create();
        $otherLocation = Location::query()->create([
            'name' => 'Sala Alta Organizatie '.fake()->unique()->numerify('###'),
            'organization_id' => $otherOrganization->id,
        ]);
        $otherInstructor = User::factory()->create(['organization_id' => $otherOrganization->id]);
        $otherGroup = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Grupa alta organizatie',
            'organization_id' => $otherOrganization->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload(['location_id' => $otherLocation->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload(['instructor_id' => $otherInstructor->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['instructor_id']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload(['group_id' => $otherGroup->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_id']);
    }

    public function test_events_are_isolated_per_organization(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $outsider = User::factory()->create();
        $otherEvent = Event::query()->create($this->eventData($outsider->organization_id, [
            'recurrence_type' => 'once',
            'start_date' => '2026-10-05',
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/events/{$otherEvent->id}")
            ->assertNotFound();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$otherEvent->id}", ['title' => 'Hacked'])
            ->assertNotFound();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/events/{$otherEvent->id}")
            ->assertNotFound();

        $listedIds = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/events')
            ->assertOk()
            ->json('data.*.id');

        $this->assertNotContains($otherEvent->id, $listedIds);
    }

    private function eventData(int $organizationId, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organizationId,
            'title' => 'Eveniment alta organizatie',
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
