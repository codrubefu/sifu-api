<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventAttendancePdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_participants_view_right_can_download_occurrence_attendance_pdf(): void
    {
        [$operator, $token] = $this->loginWith('event_participants.view');
        $occurrence = $this->occurrence($operator);
        $participant = User::factory()->create([
            'organization_id' => $operator->organization_id,
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
        ]);
        $occurrence->participants()->attach($participant, [
            'status' => 'attended',
            'registered_at' => '2026-09-10 08:30:00',
            'notes' => 'Prezenta confirmata',
        ]);

        $this->withToken($token)
            ->get("/api/event-occurrences/{$occurrence->id}/participants/download/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="prezenta-eveniment-'.$occurrence->id.'.pdf"');
    }

    public function test_download_requires_participant_view_or_manage_right(): void
    {
        [$operator, $token] = $this->loginWith('events.view');
        $occurrence = $this->occurrence($operator);

        $this->withToken($token)
            ->get("/api/event-occurrences/{$occurrence->id}/participants/download/pdf")
            ->assertForbidden();
    }

    public function test_download_cannot_access_occurrence_from_another_organization(): void
    {
        [, $token] = $this->loginWith('event_participants.view');
        $outsider = User::factory()->create();
        $occurrence = $this->occurrence($outsider);

        $this->withToken($token)
            ->get("/api/event-occurrences/{$occurrence->id}/participants/download/pdf")
            ->assertNotFound();
    }

    private function occurrence(User $owner): EventOccurrence
    {
        $event = Event::query()->create([
            'organization_id' => $owner->organization_id,
            'title' => 'Yoga',
            'location' => 'Studio A',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'recurrence_type' => 'once',
            'start_date' => '2026-09-10',
            'max_participants' => 20,
            'status' => 'active',
        ]);

        return EventOccurrence::query()->create([
            'organization_id' => $owner->organization_id,
            'event_id' => $event->id,
            'occurrence_date' => '2026-09-10',
            'start_datetime' => '2026-09-10 09:00:00',
            'end_datetime' => '2026-09-10 10:00:00',
            'status' => 'scheduled',
        ]);
    }

    private function loginWith(string $rightName): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $right = Right::query()->firstOrCreate(['name' => $rightName], ['label' => $rightName]);
        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Events',
            'organization_id' => $user->organization_id,
        ]);
        $group->rights()->attach($right);
        $user->groups()->attach($group);
        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'password' => 'password',
        ])->json('token');

        return [$user, $token];
    }
}
