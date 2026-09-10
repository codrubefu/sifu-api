<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventCategory;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Group;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventParticipationReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_capacity_registrations_attendance_and_utilization(): void
    {
        [$operator, $token] = $this->loginWithReportsRight();
        $category = EventCategory::query()->create([
            'organization_id' => $operator->organization_id,
            'name' => 'Yoga',
            'color' => '#ffffff',
            'is_active' => true,
        ]);

        $first = $this->occurrence($operator, $category, 2);
        $second = $this->occurrence($operator, $category, 2);
        $participants = User::factory()->count(4)->create(['organization_id' => $operator->organization_id]);
        $first->participants()->attach($participants[0], ['status' => 'attended']);
        $first->participants()->attach($participants[1], ['status' => 'registered']);
        $second->participants()->attach($participants[2], ['status' => 'attended']);
        $second->participants()->attach($participants[3], ['status' => 'cancelled']);

        $this->withToken($token)->getJson('/api/reports/event-participation?from=2026-09-10&to=2026-09-10')
            ->assertOk()
            ->assertJsonCount(1, 'data.groups')
            ->assertJsonPath('data.groups.0.event.id', $first->event_id)
            ->assertJsonPath('data.groups.0.category.name', 'Yoga')
            ->assertJsonPath('data.groups.0.location', 'Studio A')
            ->assertJsonPath('data.groups.0.day', '2026-09-10')
            ->assertJsonPath('data.groups.0.time_interval.from', '09:00')
            ->assertJsonPath('data.groups.0.sessions', 2)
            ->assertJsonPath('data.groups.0.capacity', 4)
            ->assertJsonPath('data.groups.0.registrations', 3)
            ->assertJsonPath('data.groups.0.attendances', 2)
            ->assertJsonPath('data.groups.0.occupancy_percentage', 75)
            ->assertJsonPath('data.groups.0.utilization', 'normal');
    }

    public function test_it_highlights_full_and_underutilized_sessions_and_keeps_tenants_isolated(): void
    {
        [$operator, $token] = $this->loginWithReportsRight();
        $category = EventCategory::query()->create([
            'organization_id' => $operator->organization_id, 'name' => 'Fitness', 'is_active' => true,
        ]);
        $full = $this->occurrence($operator, $category, 1, '09:00:00', '10:00:00');
        $underused = $this->occurrence($operator, $category, 10, '11:00:00', '12:00:00');
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);
        $full->participants()->attach($member, ['status' => 'registered']);

        $outsider = User::factory()->create();
        $otherCategory = EventCategory::query()->create([
            'organization_id' => $outsider->organization_id, 'name' => 'Private', 'is_active' => true,
        ]);
        $this->occurrence($outsider, $otherCategory, 1);

        $response = $this->withToken($token)->getJson('/api/reports/event-participation?underutilized_below=25')
            ->assertOk()->assertJsonCount(2, 'data.groups');

        $this->assertSame(['full', 'underutilized'], array_column($response->json('data.groups'), 'utilization'));
        $response->assertJsonMissing(['name' => 'Private']);
        $this->withToken($token)->getJson('/api/reports/event-participation?organization_id='.$outsider->organization_id)
            ->assertForbidden();
    }

    private function occurrence(User $owner, EventCategory $category, int $capacity, string $start = '09:00:00', string $end = '10:00:00'): EventOccurrence
    {
        $event = Event::query()->create([
            'organization_id' => $owner->organization_id,
            'category_id' => $category->id,
            'title' => fake()->unique()->sentence(2),
            'location' => 'Studio A',
            'start_time' => $start,
            'end_time' => $end,
            'recurrence_type' => 'once',
            'start_date' => '2026-09-10',
            'max_participants' => $capacity,
            'status' => 'active',
        ]);

        return EventOccurrence::query()->create([
            'organization_id' => $owner->organization_id,
            'event_id' => $event->id,
            'occurrence_date' => '2026-09-10',
            'start_datetime' => "2026-09-10 {$start}",
            'end_datetime' => "2026-09-10 {$end}",
            'status' => 'scheduled',
        ]);
    }

    private function loginWithReportsRight(): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $right = Right::query()->create(['name' => 'reports.view', 'label' => 'View reports']);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Reports']);
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
