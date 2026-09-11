<?php

namespace Tests\Feature\Events;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventOccurrenceCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_manage_right_can_cancel_scheduled_occurrence_and_notifies_participants(): void
    {
        Queue::fake();

        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);
        $participant = User::factory()->create([
            'organization_id' => $admin->organization_id,
            'phone' => '0712345678',
            'notification_consents' => ['sms' => true, 'mail' => false, 'push' => false],
        ]);
        $event = Event::query()->create($this->eventData($admin->organization_id));
        $occurrence = $this->occurrence($event, $admin->organization_id);
        $occurrence->participants()->attach($participant->id, [
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-occurrences/{$occurrence->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.id', $occurrence->id)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('event_occurrences', [
            'id' => $occurrence->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $participant->id,
            'event_type' => 'occurrence.cancelled',
            'channel' => 'sms',
        ]);
    }

    public function test_cancelling_occurrence_requires_manage_right(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.view']);
        $event = Event::query()->create($this->eventData($admin->organization_id));
        $occurrence = $this->occurrence($event, $admin->organization_id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-occurrences/{$occurrence->id}/cancel")
            ->assertForbidden();
    }

    public function test_occurrence_cannot_be_cancelled_twice(): void
    {
        Queue::fake();

        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);
        $event = Event::query()->create($this->eventData($admin->organization_id));
        $occurrence = $this->occurrence($event, $admin->organization_id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-occurrences/{$occurrence->id}/cancel")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-occurrences/{$occurrence->id}/cancel")
            ->assertBadRequest()
            ->assertJsonPath('message', 'Event occurrence is already cancelled or completed.');
    }

    public function test_completed_occurrence_cannot_be_cancelled(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);
        $event = Event::query()->create($this->eventData($admin->organization_id));
        $occurrence = $this->occurrence($event, $admin->organization_id, ['status' => 'completed']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-occurrences/{$occurrence->id}/cancel")
            ->assertBadRequest();
    }

    public function test_occurrence_from_another_organization_cannot_be_cancelled(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['events.manage']);

        $otherOrganization = Organization::factory()->create();
        $otherEvent = Event::query()->create($this->eventData($otherOrganization->id));
        $otherOccurrence = $this->occurrence($otherEvent, $otherOrganization->id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/event-occurrences/{$otherOccurrence->id}/cancel")
            ->assertNotFound();
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

    private function occurrence(Event $event, int $organizationId, array $overrides = []): EventOccurrence
    {
        return EventOccurrence::query()->create(array_merge([
            'event_id' => $event->id,
            'organization_id' => $organizationId,
            'occurrence_date' => '2026-06-10',
            'start_datetime' => '2026-06-10 10:00:00',
            'end_datetime' => '2026-06-10 11:00:00',
            'status' => 'scheduled',
        ], $overrides));
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
