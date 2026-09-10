<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Service\Models\Service;
use App\Users\Models\AuditLog;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_search_and_confirm_valid_member_check_in(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['checkins.manage']);
        $member = User::factory()->create(['organization_id' => $admin->organization_id, 'user_code' => 'CARD-100']);
        $service = Service::query()->create($this->serviceData($admin->organization_id, ['max_accesses' => 3]));
        $member->services()->attach($service->id, [
            'status' => 'active',
            'start_date' => now()->subDay(),
            'accesses_used' => 0,
            'activated_at' => now()->subDay(),
        ]);
        $occurrence = $this->occurrence($this->event(['requires_active_service' => true, 'required_service_id' => $service->id]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/search', ['query' => 'CARD-100', 'occurrence_id' => $occurrence->id])
            ->assertOk()
            ->assertJsonPath('data.member_found', true)
            ->assertJsonPath('data.access_allowed', true)
            ->assertJsonPath('data.active_subscription', true);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/confirm', ['user_id' => $member->id, 'occurrence_id' => $occurrence->id])
            ->assertOk()
            ->assertJsonPath('data.verdict', 'allowed')
            ->assertJsonPath('data.participant.status', 'attended');

        $this->assertDatabaseHas('event_occurrence_user', ['event_occurrence_id' => $occurrence->id, 'user_id' => $member->id, 'status' => 'attended']);
        $this->assertDatabaseHas('service_user', ['user_id' => $member->id, 'service_id' => $service->id, 'accesses_used' => 1]);
        $this->assertDatabaseHas('audit_logs', ['event_type' => AuditLog::CHECKIN_ACCEPTED, 'subject_user_id' => $member->id]);
    }

    public function test_check_in_refuses_member_without_required_service(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['checkins.manage']);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData($admin->organization_id));
        $occurrence = $this->occurrence($this->event(['requires_active_service' => true, 'required_service_id' => $service->id]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/confirm', ['user_id' => $member->id, 'occurrence_id' => $occurrence->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['access']);

        $this->assertDatabaseMissing('event_occurrence_user', ['event_occurrence_id' => $occurrence->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('audit_logs', ['event_type' => AuditLog::CHECKIN_REFUSED, 'subject_user_id' => $member->id]);
    }

    public function test_operator_with_override_right_can_check_in_member_without_required_service(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['checkins.manage', 'checkins.override']);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $service = Service::query()->create($this->serviceData($admin->organization_id, ['max_accesses' => 3]));
        $occurrence = $this->occurrence($this->event(['requires_active_service' => true, 'required_service_id' => $service->id]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/confirm', [
                'user_id' => $member->id,
                'occurrence_id' => $occurrence->id,
                'allow_override' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.verdict', 'override_allowed')
            ->assertJsonPath('data.participant.status', 'attended');

        $this->assertDatabaseHas('event_occurrence_user', [
            'event_occurrence_id' => $occurrence->id,
            'user_id' => $member->id,
            'status' => 'attended',
        ]);
        $this->assertDatabaseMissing('service_user', [
            'user_id' => $member->id,
            'service_id' => $service->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event_type' => AuditLog::CHECKIN_ACCEPTED, 'subject_user_id' => $member->id]);
    }

    public function test_search_does_not_expose_member_from_another_organization(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['checkins.manage']);
        $otherOrganization = Organization::factory()->create();
        User::factory()->create(['organization_id' => $otherOrganization->id, 'user_code' => 'OTHER-CARD']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/search', ['query' => 'OTHER-CARD'])
            ->assertOk()
            ->assertJsonPath('data.member_found', false);
    }

    public function test_check_in_requires_manage_right(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.view']);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $occurrence = $this->occurrence($this->event());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/confirm', ['user_id' => $member->id, 'occurrence_id' => $occurrence->id])
            ->assertForbidden();
    }

    public function test_current_check_in_occurrences_include_all_scheduled_classes_for_today(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['checkins.manage']);
        $event = $this->event();
        $early = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => now()->toDateString(),
            'start_datetime' => now()->setTime(7, 0),
            'end_datetime' => now()->setTime(8, 0),
            'status' => 'scheduled',
        ]);
        $late = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => now()->toDateString(),
            'start_datetime' => now()->setTime(21, 0),
            'end_datetime' => now()->setTime(22, 0),
            'status' => 'scheduled',
        ]);
        $tomorrow = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => now()->addDay()->toDateString(),
            'start_datetime' => now()->addDay()->setTime(10, 0),
            'end_datetime' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/check-ins/occurrences/current')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($early->id));
        $this->assertTrue($ids->contains($late->id));
        $this->assertFalse($ids->contains($tomorrow->id));
    }

    public function test_duplicate_check_in_returns_already_present_without_new_row(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['checkins.manage']);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $occurrence = $this->occurrence($this->event());
        $occurrence->participants()->attach($member->id, ['status' => 'attended', 'registered_at' => now()]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/confirm', ['user_id' => $member->id, 'occurrence_id' => $occurrence->id])
            ->assertOk()
            ->assertJsonPath('data.verdict', 'already_present');

        $this->assertSame(1, $occurrence->participants()->whereKey($member->id)->count());
        $this->assertDatabaseHas('audit_logs', ['event_type' => AuditLog::CHECKIN_REFUSED, 'subject_user_id' => $member->id]);
    }

    private function event(array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'title' => 'Clasa test',
            'location' => 'Sala 1',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => now()->toDateString(),
            'requires_active_service' => false,
            'required_service_id' => null,
            'requires_payment' => false,
            'max_participants' => null,
            'status' => 'active',
        ], $overrides));
    }

    private function occurrence(Event $event): EventOccurrence
    {
        return EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => now()->toDateString(),
            'start_datetime' => now()->subMinutes(30),
            'end_datetime' => now()->addMinutes(30),
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
        $user = User::factory()->create(['password' => 'password']);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Test Group']);

        foreach ($rightNames as $rightName) {
            $right = Right::query()->firstOrCreate(['name' => $rightName], ['label' => $rightName]);
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
