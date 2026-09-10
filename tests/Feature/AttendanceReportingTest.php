<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_aggregates_attendance_and_applies_all_filters(): void
    {
        [$operator, $token] = $this->loginWith('reports.view');
        $member = User::factory()->create(['organization_id' => $operator->organization_id]);
        $instructor = User::factory()->create(['organization_id' => $operator->organization_id]);
        $location = Location::query()->create(['name' => 'Studio', 'organization_id' => $operator->organization_id]);
        $group = Group::query()->create(['name' => 'juniors', 'label' => 'Juniors', 'organization_id' => $operator->organization_id]);
        $categoryId = \DB::table('event_categories')->insertGetId([
            'organization_id' => $operator->organization_id, 'name' => 'Yoga', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = Event::query()->create($this->eventData($operator->organization_id, $location->id, $instructor->id, $group->id, $categoryId));
        $first = EventOccurrence::query()->create($this->occurrenceData($event, '2026-08-10'));
        $second = EventOccurrence::query()->create($this->occurrenceData($event, '2026-08-17'));
        $first->participants()->attach($member, ['status' => 'attended']);
        $second->participants()->attach($member, ['status' => 'no_show']);

        $query = http_build_query([
            'from' => '2026-08-01', 'to' => '2026-08-31', 'location_id' => $location->id,
            'category_id' => $categoryId, 'instructor_id' => $instructor->id,
            'group_id' => $group->id, 'member_id' => $member->id,
        ]);

        $this->withToken($token)->getJson('/api/reports/attendance?'.$query)
            ->assertOk()
            ->assertJsonPath('data.sessions', 2)
            ->assertJsonPath('data.attendances', 1)
            ->assertJsonPath('data.absences', 1)
            ->assertJsonPath('data.participation_rate', 50);
    }

    public function test_export_supports_csv_and_requires_export_right(): void
    {
        [, $viewToken] = $this->loginWith('reports.view');
        $this->withToken($viewToken)->get('/api/reports/attendance/export?format=csv')->assertForbidden();

        [, $exportToken] = $this->loginWith('reports.export');
        $this->withToken($exportToken)->get('/api/reports/attendance/export?format=csv')
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('sessions,attendances,absences,participation_rate', false);
        $this->withToken($exportToken)->get('/api/reports/attendance/export?format=xlsx')
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function loginWith(string $rightName): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $right = Right::query()->firstOrCreate(['name' => $rightName], ['label' => $rightName]);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Reports', 'organization_id' => $user->organization_id]);
        $group->rights()->attach($right);
        $user->groups()->attach($group);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'organization_id' => $user->organization_id, 'password' => 'password'])->json('token');

        return [$user, $token];
    }

    private function eventData(int $organizationId, int $locationId, int $instructorId, int $groupId, int $categoryId): array
    {
        return [
            'organization_id' => $organizationId, 'category_id' => $categoryId, 'location_id' => $locationId,
            'instructor_id' => $instructorId, 'group_id' => $groupId, 'title' => 'Yoga class',
            'start_time' => '10:00', 'end_time' => '11:00', 'recurrence_type' => 'weekly',
            'start_date' => '2026-08-01', 'status' => 'active',
        ];
    }

    private function occurrenceData(Event $event, string $date): array
    {
        return [
            'organization_id' => $event->organization_id, 'event_id' => $event->id,
            'occurrence_date' => $date, 'start_datetime' => $date.' 10:00:00',
            'end_datetime' => $date.' 11:00:00', 'status' => 'completed',
        ];
    }
}
