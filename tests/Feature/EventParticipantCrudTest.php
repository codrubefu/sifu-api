<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventParticipantCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_manage_right_can_create_paid_event(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', [
                'title' => 'Eveniment platit',
                'description' => 'Descriere eveniment',
                'location' => 'Sala 2',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'recurrence_type' => 'once',
                'start_date' => '2026-06-10',
                'status' => 'active',
                'requires_payment' => true,
                'payment_amount' => 49.99,
                'payment_type' => 'card',
            ])
            ->assertCreated()
            ->assertJsonPath('data.requires_payment', true)
            ->assertJsonPath('data.payment_amount', '49.99')
            ->assertJsonPath('data.payment_type', 'card');

        $this->assertDatabaseHas('events', [
            'title' => 'Eveniment platit',
            'requires_payment' => true,
            'payment_amount' => 49.99,
            'payment_type' => 'card',
        ]);
    }

    public function test_paid_event_requires_amount_and_type(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', [
                'title' => 'Eveniment platit',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'recurrence_type' => 'once',
                'start_date' => '2026-06-10',
                'status' => 'active',
                'requires_payment' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'payment_amount',
                'payment_type',
            ]);
    }

    public function test_user_with_manage_right_can_update_occurrence_participant(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $participant = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData([
            'max_participants' => 10,
        ]));
        $occurrence = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => '2026-06-10',
            'start_datetime' => '2026-06-10 10:00:00',
            'end_datetime' => '2026-06-10 11:00:00',
            'status' => 'scheduled',
        ]);

        $occurrence->participants()->attach($participant->id, [
            'status' => 'registered',
            'registered_at' => '2026-06-01 09:00:00',
            'notes' => 'Inscris initial.',
        ]);
        $pivotId = DB::table('event_occurrence_user')
            ->where('event_occurrence_id', $occurrence->id)
            ->where('user_id', $participant->id)
            ->value('id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-occurrences/{$occurrence->id}/participants/{$participant->id}", [
                'status' => 'attended',
                'notes' => 'A intarziat',
            ])
            ->assertOk()
            ->assertJsonPath('data.pivot_id', $pivotId)
            ->assertJsonPath('data.user_id', $participant->id)
            ->assertJsonPath('data.status', 'attended')
            ->assertJsonPath('data.notes', 'A intarziat');

        $this->assertDatabaseHas('event_occurrence_user', [
            'event_occurrence_id' => $occurrence->id,
            'user_id' => $participant->id,
            'status' => 'attended',
            'notes' => 'A intarziat',
        ]);
    }

    public function test_user_with_view_right_can_list_eligible_occurrence_participants(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.view']);
        $eligible = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana.popescu@example.com',
            'phone' => '0700000001',
            'user_code' => 'CARD-ANA',
        ]);
        $alreadyParticipant = User::factory()->create(['organization_id' => $admin->organization_id]);
        User::factory()->create([
            'first_name' => 'Ana',
            'last_name' => 'Other Org',
            'email' => 'ana.other@example.com',
        ]);
        $event = Event::query()->create($this->eventData());
        $occurrence = $this->occurrence($event);
        $occurrence->participants()->attach($alreadyParticipant->id, ['status' => 'registered']);

        foreach (['Ana', 'ana.popescu@example.com', '0700000001', 'CARD-ANA'] as $search) {
            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson("/api/event-occurrences/{$occurrence->id}/eligible-participants?search={$search}&per_page=10");

            $response->assertOk();

            $ids = collect($response->json('data'))->pluck('id');
            $this->assertTrue($ids->contains($eligible->id));
            $this->assertFalse($ids->contains($alreadyParticipant->id));
        }
    }

    public function test_eligible_occurrence_participants_requires_active_service_when_configured(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.view']);
        $requiredService = Service::query()->create($this->serviceData($admin->organization_id));
        $eligible = User::factory()->create(['organization_id' => $admin->organization_id]);
        $ineligible = User::factory()->create(['organization_id' => $admin->organization_id]);
        $eligible->services()->attach($requiredService->id, [
            'status' => 'active',
            'start_date' => now()->subDay(),
            'activated_at' => now()->subDay(),
        ]);
        $event = Event::query()->create($this->eventData([
            'requires_active_service' => true,
            'required_service_id' => $requiredService->id,
        ]));
        $occurrence = $this->occurrence($event);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/event-occurrences/{$occurrence->id}/eligible-participants?per_page=20");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($eligible->id));
        $this->assertFalse($ids->contains($ineligible->id));
    }

    public function test_adding_occurrence_participant_without_status_defaults_to_registered(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $participant = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData());
        $occurrence = $this->occurrence($event);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", [
                'user_id' => $participant->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'registered');

        $this->assertDatabaseHas('event_occurrence_user', [
            'event_occurrence_id' => $occurrence->id,
            'user_id' => $participant->id,
            'status' => 'registered',
        ]);
    }

    public function test_adding_event_participant_consumes_one_required_service_access(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $participant = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData($admin->organization_id, [
            'max_accesses' => 3,
        ]));
        $participant->services()->attach($service->id, [
            'status' => 'active',
            'start_date' => now()->subDay(),
            'accesses_used' => 0,
            'activated_at' => now()->subDay(),
        ]);
        $event = Event::query()->create($this->eventData([
            'requires_active_service' => true,
            'required_service_id' => $service->id,
        ]));
        $occurrence = $this->occurrence($event);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $participant->id])
            ->assertCreated();

        $this->assertDatabaseHas('service_user', [
            'user_id' => $participant->id,
            'service_id' => $service->id,
            'accesses_used' => 1,
            'status' => 'active',
        ]);
    }

    public function test_bulk_event_participants_consume_one_access_per_user(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $second = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData($admin->organization_id, ['max_accesses' => 2]));
        foreach ([$first, $second] as $participant) {
            $participant->services()->attach($service->id, [
                'status' => 'active',
                'start_date' => now()->subDay(),
                'accesses_used' => 0,
                'activated_at' => now()->subDay(),
            ]);
        }
        $event = Event::query()->create($this->eventData([
            'requires_active_service' => true,
            'required_service_id' => $service->id,
            'max_participants' => 2,
        ]));
        $occurrence = $this->occurrence($event);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/bulk", ['user_ids' => [$first->id, $second->id]])
            ->assertCreated();

        foreach ([$first, $second] as $participant) {
            $this->assertDatabaseHas('service_user', [
                'user_id' => $participant->id,
                'service_id' => $service->id,
                'accesses_used' => 1,
            ]);
        }
    }

    public function test_user_with_manage_right_can_bulk_add_occurrence_participants(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $second = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData(['max_participants' => 2]));
        $occurrence = $this->occurrence($event);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/bulk", [
                'user_ids' => [$first->id, $second->id],
                'notes' => 'Adaugati rapid.',
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'registered');

        foreach ([$first, $second] as $participant) {
            $this->assertDatabaseHas('event_occurrence_user', [
                'event_occurrence_id' => $occurrence->id,
                'user_id' => $participant->id,
                'status' => 'registered',
                'notes' => 'Adaugati rapid.',
            ]);
        }
    }

    public function test_bulk_add_occurrence_participants_respects_available_places(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $second = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData(['max_participants' => 1]));
        $occurrence = $this->occurrence($event);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/bulk", [
                'user_ids' => [$first->id, $second->id],
            ])
            ->assertBadRequest()
            ->assertJsonPath('message', 'Event occurrence does not have enough available places.');

        $this->assertDatabaseMissing('event_occurrence_user', [
            'event_occurrence_id' => $occurrence->id,
            'user_id' => $first->id,
        ]);
    }

    private function eventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Eveniment test',
            'description' => 'Descriere eveniment',
            'location' => 'Sala 1',
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

    private function occurrence(Event $event): EventOccurrence
    {
        return EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => '2026-06-10',
            'start_datetime' => '2026-06-10 10:00:00',
            'end_datetime' => '2026-06-10 11:00:00',
            'status' => 'scheduled',
        ]);
    }

    private function serviceData(int $organizationId, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organizationId,
            'name' => 'Abonament activ',
            'description' => 'Test',
            'type' => 'membership',
            'price' => 0,
            'currency' => 'RON',
            'duration_days' => 30,
            'expiration_rule' => 'duration',
            'fixed_expires_at' => null,
            'grace_period_days' => 0,
            'max_accesses' => null,
            'max_users' => null,
            'is_active' => true,
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
