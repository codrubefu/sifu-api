<?php

namespace Tests\Feature\Events;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventParticipantBulkFutureTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_to_future_occurrences_adds_users_to_all_future_scheduled_occurrences_only(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $second = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData($admin->organization_id, ['recurrence_type' => 'weekly']));

        $target = $this->occurrence($event, $admin->organization_id, '2026-09-10');
        $future1 = $this->occurrence($event, $admin->organization_id, '2026-09-17');
        $future2 = $this->occurrence($event, $admin->organization_id, '2026-09-24');
        $past = $this->occurrence($event, $admin->organization_id, '2026-09-03');
        $cancelledFuture = $this->occurrence($event, $admin->organization_id, '2026-09-20', 'cancelled');
        $completedFuture = $this->occurrence($event, $admin->organization_id, '2026-09-21', 'completed');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$target->id}/participants/bulk", [
                'user_ids' => [$first->id, $second->id],
                'apply_to_future_occurrences' => true,
            ])
            ->assertCreated();

        $response->assertJsonCount(2, 'future_occurrences_updated');

        $summary = collect($response->json('future_occurrences_updated'))->keyBy('occurrence_id');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $summary[$future1->id]['added_user_ids']);
        $this->assertSame([], $summary[$future1->id]['skipped']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $summary[$future2->id]['added_user_ids']);
        $this->assertSame([], $summary[$future2->id]['skipped']);

        foreach ([$target, $future1, $future2] as $occurrence) {
            foreach ([$first, $second] as $participant) {
                $this->assertDatabaseHas('event_occurrence_user', [
                    'event_occurrence_id' => $occurrence->id,
                    'user_id' => $participant->id,
                ]);
            }
        }

        foreach ([$past, $cancelledFuture, $completedFuture] as $occurrence) {
            foreach ([$first, $second] as $participant) {
                $this->assertDatabaseMissing('event_occurrence_user', [
                    'event_occurrence_id' => $occurrence->id,
                    'user_id' => $participant->id,
                ]);
            }
        }
    }

    public function test_future_occurrence_without_capacity_is_skipped_without_failing_the_whole_request(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $second = User::factory()->create(['organization_id' => $admin->organization_id]);
        $fillerA = User::factory()->create(['organization_id' => $admin->organization_id]);
        $fillerB = User::factory()->create(['organization_id' => $admin->organization_id]);

        $event = Event::query()->create($this->eventData($admin->organization_id, [
            'recurrence_type' => 'weekly',
            'max_participants' => 2,
        ]));

        $target = $this->occurrence($event, $admin->organization_id, '2026-09-10');
        $fullFuture = $this->occurrence($event, $admin->organization_id, '2026-09-17');
        $openFuture = $this->occurrence($event, $admin->organization_id, '2026-09-24');

        $fullFuture->participants()->attach([
            $fillerA->id => ['status' => 'registered', 'registered_at' => now()],
            $fillerB->id => ['status' => 'registered', 'registered_at' => now()],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$target->id}/participants/bulk", [
                'user_ids' => [$first->id, $second->id],
                'apply_to_future_occurrences' => true,
            ])
            ->assertCreated();

        $summary = collect($response->json('future_occurrences_updated'))->keyBy('occurrence_id');

        $this->assertSame([], $summary[$fullFuture->id]['added_user_ids']);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            collect($summary[$fullFuture->id]['skipped'])->pluck('user_id')->all()
        );
        foreach ($summary[$fullFuture->id]['skipped'] as $skip) {
            $this->assertSame('capacity_full', $skip['reason']);
        }

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $summary[$openFuture->id]['added_user_ids']);
        $this->assertSame([], $summary[$openFuture->id]['skipped']);

        foreach ([$first, $second] as $participant) {
            $this->assertDatabaseMissing('event_occurrence_user', [
                'event_occurrence_id' => $fullFuture->id,
                'user_id' => $participant->id,
            ]);
            $this->assertDatabaseHas('event_occurrence_user', [
                'event_occurrence_id' => $openFuture->id,
                'user_id' => $participant->id,
            ]);
            $this->assertDatabaseHas('event_occurrence_user', [
                'event_occurrence_id' => $target->id,
                'user_id' => $participant->id,
            ]);
        }
    }

    public function test_user_already_registered_on_future_occurrence_is_skipped_but_others_are_added(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $second = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData($admin->organization_id, ['recurrence_type' => 'weekly']));

        $target = $this->occurrence($event, $admin->organization_id, '2026-09-10');
        $future = $this->occurrence($event, $admin->organization_id, '2026-09-17');

        $future->participants()->attach($second->id, ['status' => 'registered', 'registered_at' => now()]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$target->id}/participants/bulk", [
                'user_ids' => [$first->id, $second->id],
                'apply_to_future_occurrences' => true,
            ])
            ->assertCreated();

        $summary = collect($response->json('future_occurrences_updated'))->keyBy('occurrence_id');

        $this->assertSame([$first->id], $summary[$future->id]['added_user_ids']);
        $this->assertCount(1, $summary[$future->id]['skipped']);
        $this->assertSame($second->id, $summary[$future->id]['skipped'][0]['user_id']);
        $this->assertSame('already_registered', $summary[$future->id]['skipped'][0]['reason']);

        $this->assertDatabaseHas('event_occurrence_user', [
            'event_occurrence_id' => $future->id,
            'user_id' => $first->id,
        ]);
    }

    public function test_apply_to_future_occurrences_does_not_touch_another_organization_event(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData($admin->organization_id, ['recurrence_type' => 'weekly']));
        $target = $this->occurrence($event, $admin->organization_id, '2026-09-10');
        $future = $this->occurrence($event, $admin->organization_id, '2026-09-17');

        $otherOrganization = Organization::factory()->create();
        $otherEvent = Event::query()->create($this->eventData($otherOrganization->id, ['recurrence_type' => 'weekly']));
        $otherFuture = $this->occurrence($otherEvent, $otherOrganization->id, '2026-09-17');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$target->id}/participants/bulk", [
                'user_ids' => [$first->id],
                'apply_to_future_occurrences' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('event_occurrence_user', [
            'event_occurrence_id' => $future->id,
            'user_id' => $first->id,
        ]);
        $this->assertDatabaseMissing('event_occurrence_user', [
            'event_occurrence_id' => $otherFuture->id,
            'user_id' => $first->id,
        ]);
    }

    public function test_apply_to_future_occurrences_absent_keeps_response_unchanged(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData($admin->organization_id, ['recurrence_type' => 'weekly']));
        $target = $this->occurrence($event, $admin->organization_id, '2026-09-10');
        $this->occurrence($event, $admin->organization_id, '2026-09-17');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$target->id}/participants/bulk", [
                'user_ids' => [$first->id],
            ])
            ->assertCreated()
            ->assertJsonMissingPath('future_occurrences_updated');
    }

    public function test_apply_to_future_occurrences_false_keeps_response_unchanged(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = Event::query()->create($this->eventData($admin->organization_id, ['recurrence_type' => 'weekly']));
        $target = $this->occurrence($event, $admin->organization_id, '2026-09-10');
        $future = $this->occurrence($event, $admin->organization_id, '2026-09-17');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$target->id}/participants/bulk", [
                'user_ids' => [$first->id],
                'apply_to_future_occurrences' => false,
            ])
            ->assertCreated()
            ->assertJsonMissingPath('future_occurrences_updated');

        $this->assertDatabaseMissing('event_occurrence_user', [
            'event_occurrence_id' => $future->id,
            'user_id' => $first->id,
        ]);
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
            'start_date' => '2026-09-01',
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

    private function occurrence(Event $event, int $organizationId, string $date, string $status = 'scheduled'): EventOccurrence
    {
        return EventOccurrence::query()->create([
            'event_id' => $event->id,
            'organization_id' => $organizationId,
            'occurrence_date' => $date,
            'start_datetime' => "{$date} 10:00:00",
            'end_datetime' => "{$date} 11:00:00",
            'status' => $status,
        ]);
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
