<?php

namespace Tests\Feature\Events;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Service\Models\Service;
use App\Service\Services\ServiceLifecycleService;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Events with several required services: OR inside a group, AND between groups,
 * e.g. (monthly OR yearly fee) AND FRAM fee.
 */
class EventServiceGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_is_created_with_service_groups_and_returns_service_summaries(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);
        [$monthly, $yearly, $fram] = $this->services($admin->organization_id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'requires_active_service' => true,
                'service_groups' => [[$monthly->id, $yearly->id], [], [$fram->id]],
            ]))
            ->assertCreated()
            ->assertJsonMissingPath('data.required_service_id')
            ->assertJsonMissingPath('data.required_service')
            ->assertJsonCount(2, 'data.service_groups')
            ->assertJsonCount(2, 'data.service_groups.0')
            ->assertJsonCount(1, 'data.service_groups.1')
            ->assertJsonPath('data.service_groups.0.0.id', $monthly->id)
            ->assertJsonPath('data.service_groups.0.0.name', 'Taxa lunara')
            ->assertJsonPath('data.service_groups.0.1.id', $yearly->id)
            ->assertJsonPath('data.service_groups.1.0.id', $fram->id)
            ->assertJsonPath('data.service_groups.1.0.max_accesses', 10);

        $eventId = $response->json('data.id');
        $this->assertDatabaseHas('event_service_requirements', ['event_id' => $eventId, 'group_index' => 0, 'service_id' => $yearly->id]);
        $this->assertDatabaseHas('event_service_requirements', ['event_id' => $eventId, 'group_index' => 1, 'service_id' => $fram->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/events/{$eventId}")
            ->assertOk()
            ->assertJsonPath('data.service_groups.1.0.id', $fram->id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/events')
            ->assertOk()
            ->assertJsonPath('data.0.service_groups.0.1.id', $yearly->id);
    }

    public function test_service_groups_validation_rejects_duplicates_foreign_services_and_non_arrays(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);
        [$monthly, , $fram] = $this->services($admin->organization_id);
        $otherOrganization = Organization::factory()->create();
        $foreign = Service::query()->create($this->serviceData($otherOrganization->id, ['name' => 'Strain']));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'requires_active_service' => true,
                'service_groups' => [[$monthly->id], [$monthly->id, $fram->id]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_groups.0.0', 'service_groups.1.0']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'requires_active_service' => true,
                'service_groups' => [[$foreign->id]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_groups.0.0']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'requires_active_service' => true,
                'service_groups' => [$monthly->id],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_groups.0']);

        $this->assertDatabaseCount('event_service_requirements', 0);
    }

    public function test_groups_are_cleared_without_requires_active_service_and_kept_on_partial_update(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['events.manage']);
        [$monthly, $yearly, $fram] = $this->services($admin->organization_id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'requires_active_service' => false,
                'service_groups' => [[$monthly->id]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.service_groups', []);

        $eventId = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/events', $this->eventPayload([
                'requires_active_service' => true,
                'service_groups' => [[$monthly->id]],
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$eventId}", ['title' => 'Titlu nou'])
            ->assertOk()
            ->assertJsonPath('data.service_groups.0.0.id', $monthly->id);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/events/{$eventId}", ['service_groups' => [[$yearly->id], [$fram->id]]])
            ->assertOk()
            ->assertJsonCount(2, 'data.service_groups')
            ->assertJsonPath('data.service_groups.0.0.id', $yearly->id)
            ->assertJsonPath('data.service_groups.1.0.id', $fram->id);
        $this->assertDatabaseMissing('event_service_requirements', ['event_id' => $eventId, 'service_id' => $monthly->id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/events/{$eventId}", ['requires_active_service' => false])
            ->assertOk()
            ->assertJsonPath('data.service_groups', []);
        $this->assertDatabaseMissing('event_service_requirements', ['event_id' => $eventId]);
    }

    public function test_member_with_one_service_from_each_group_is_eligible_and_consumes_one_access_per_group(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        [$monthly, $yearly, $fram] = $this->services($admin->organization_id, monthlyMaxAccesses: 5);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $monthly);
        $this->assign($member, $fram);
        $occurrence = $this->occurrence($this->event([[$monthly->id, $yearly->id], [$fram->id]]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $member->id])
            ->assertCreated();

        $this->assertSame(1, $this->accessesUsed($member, $monthly));
        $this->assertSame(1, $this->accessesUsed($member, $fram));
    }

    public function test_member_missing_a_group_is_refused_with_missing_service_groups(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        [$monthly, $yearly, $fram] = $this->services($admin->organization_id);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $yearly);
        $occurrence = $this->occurrence($this->event([[$monthly->id, $yearly->id], [$fram->id]]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $member->id])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'missing_required_service')
            ->assertJsonPath('missing_service_groups', [1]);

        $this->assertDatabaseMissing('event_occurrence_user', ['event_occurrence_id' => $occurrence->id, 'user_id' => $member->id]);
        $this->assertSame(0, $this->accessesUsed($member, $yearly));
    }

    public function test_unlimited_service_in_a_group_wins_and_nothing_is_consumed_from_that_group(): void
    {
        [$admin] = $this->authenticatedUserWithRights(['event_participants.manage']);
        [, , $fram] = $this->services($admin->organization_id);
        $limited = Service::query()->create($this->serviceData($admin->organization_id, ['name' => 'Pachet 8', 'max_accesses' => 8]));
        $unlimited = Service::query()->create($this->serviceData($admin->organization_id, ['name' => 'Nelimitat', 'max_accesses' => null]));
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $limited);
        $this->assign($member, $unlimited);
        $this->assign($member, $fram);
        $event = $this->event([[$limited->id, $unlimited->id], [$fram->id]]);

        $consumed = app(ServiceLifecycleService::class)->consumeEventAccess($member, $event);

        $this->assertCount(1, $consumed);
        $this->assertSame(0, $this->accessesUsed($member, $limited));
        $this->assertSame(1, $this->accessesUsed($member, $fram));
    }

    public function test_consumption_takes_the_assignment_expiring_first(): void
    {
        [$admin] = $this->authenticatedUserWithRights(['event_participants.manage']);
        $late = Service::query()->create($this->serviceData($admin->organization_id, ['name' => 'Expira tarziu', 'max_accesses' => 5]));
        $soon = Service::query()->create($this->serviceData($admin->organization_id, ['name' => 'Expira curand', 'max_accesses' => 5]));
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $late, ['expires_at' => now()->addDays(20)]);
        $this->assign($member, $soon, ['expires_at' => now()->addDays(3)]);

        app(ServiceLifecycleService::class)->consumeEventAccess($member, $this->event([[$late->id, $soon->id]]));

        $this->assertSame(1, $this->accessesUsed($member, $soon));
        $this->assertSame(0, $this->accessesUsed($member, $late));
    }

    public function test_failure_on_a_later_group_rolls_back_the_whole_consumption(): void
    {
        [$admin] = $this->authenticatedUserWithRights(['event_participants.manage']);
        [$monthly, , $fram] = $this->services($admin->organization_id, monthlyMaxAccesses: 5);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $monthly);
        // FRAM is fully used up: refresh() marks it consumed, so group 1 has nothing left.
        $this->assign($member, $fram, ['accesses_used' => 10]);
        $event = $this->event([[$monthly->id], [$fram->id]]);

        try {
            app(ServiceLifecycleService::class)->consumeEventAccess($member, $event);
            $this->fail('Expected the second group to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service', $exception->errors());
        }

        $this->assertSame(0, $this->accessesUsed($member, $monthly));
    }

    public function test_check_in_search_returns_missing_service_groups(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['checkins.manage']);
        [$monthly, $yearly, $fram] = $this->services($admin->organization_id);
        $member = User::factory()->create(['organization_id' => $admin->organization_id, 'user_code' => 'CARD-GRP']);
        $this->assign($member, $fram);
        $event = $this->event([[$monthly->id, $yearly->id], [$fram->id]]);
        $occurrence = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => now()->toDateString(),
            'start_datetime' => now()->subMinutes(30),
            'end_datetime' => now()->addMinutes(30),
            'status' => 'scheduled',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/search', ['query' => 'CARD-GRP', 'occurrence_id' => $occurrence->id])
            ->assertOk()
            ->assertJsonPath('data.verdict', 'refused')
            ->assertJsonPath('data.reason', 'missing_required_service')
            ->assertJsonPath('data.missing_service_groups', [0])
            ->assertJsonPath('data.occurrence.event.service_groups.1.0.id', $fram->id);

        $this->assign($member, $yearly);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/check-ins/search', ['query' => 'CARD-GRP', 'occurrence_id' => $occurrence->id])
            ->assertOk()
            ->assertJsonPath('data.verdict', 'allowed')
            ->assertJsonPath('data.missing_service_groups', []);
    }

    public function test_eligible_participants_require_every_group(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.view']);
        [$monthly, $yearly, $fram] = $this->services($admin->organization_id);
        $both = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($both, $yearly);
        $this->assign($both, $fram);
        $onlyFirstGroup = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($onlyFirstGroup, $monthly);
        $onlySecondGroup = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($onlySecondGroup, $fram);
        $occurrence = $this->occurrence($this->event([[$monthly->id, $yearly->id], [$fram->id]]));

        $ids = collect($this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/event-occurrences/{$occurrence->id}/eligible-participants?per_page=50")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($both->id));
        $this->assertFalse($ids->contains($onlyFirstGroup->id));
        $this->assertFalse($ids->contains($onlySecondGroup->id));
    }

    public function test_event_without_groups_still_accepts_any_active_service(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        [$monthly] = $this->services($admin->organization_id, monthlyMaxAccesses: 5);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $withoutService = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $monthly);
        $occurrence = $this->occurrence($this->event([]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $member->id])
            ->assertCreated();
        $this->assertSame(0, $this->accessesUsed($member, $monthly));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $withoutService->id])
            ->assertForbidden()
            ->assertJsonPath('missing_service_groups', []);
    }

    public function test_migration_moves_legacy_required_service_into_a_single_service_group(): void
    {
        $organization = Organization::factory()->create();
        $service = Service::query()->create($this->serviceData($organization->id));
        $migration = require database_path('migrations/2026_11_26_000000_create_event_service_requirements_table.php');

        $migration->down();
        $this->assertTrue(Schema::hasColumn('events', 'required_service_id'));
        $this->assertFalse(Schema::hasTable('event_service_requirements'));

        $legacyId = DB::table('events')->insertGetId(array_merge($this->eventRow($organization->id), ['requires_active_service' => true, 'required_service_id' => $service->id]));
        $plainId = DB::table('events')->insertGetId($this->eventRow($organization->id));

        $migration->up();

        $this->assertFalse(Schema::hasColumn('events', 'required_service_id'));
        $this->assertDatabaseHas('event_service_requirements', ['event_id' => $legacyId, 'group_index' => 0, 'service_id' => $service->id]);
        $this->assertDatabaseMissing('event_service_requirements', ['event_id' => $plainId]);
        $this->assertSame(1, DB::table('event_service_requirements')->count());
    }

    private function event(array $serviceGroups): Event
    {
        $event = Event::query()->create([
            'title' => 'Antrenament FRAM',
            'location' => 'Sala 1',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => '2026-06-10',
            'requires_active_service' => true,
            'requires_payment' => false,
            'max_participants' => null,
            'status' => 'active',
        ]);
        $event->syncServiceGroups($serviceGroups);

        return $event;
    }

    private function eventRow(int $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'title' => 'Legacy',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => '2026-06-10',
            'requires_active_service' => false,
            'requires_payment' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Antrenament FRAM',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => now()->addDay()->toDateString(),
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

    /**
     * @return array{0: Service, 1: Service, 2: Service}
     */
    private function services(int $organizationId, ?int $monthlyMaxAccesses = null): array
    {
        return [
            Service::query()->create($this->serviceData($organizationId, ['name' => 'Taxa lunara', 'max_accesses' => $monthlyMaxAccesses])),
            Service::query()->create($this->serviceData($organizationId, ['name' => 'Taxa anuala'])),
            Service::query()->create($this->serviceData($organizationId, ['name' => 'Taxa FRAM', 'max_accesses' => 10])),
        ];
    }

    private function assign(User $user, Service $service, array $overrides = []): void
    {
        $user->services()->attach($service->id, array_merge([
            'status' => 'active',
            'start_date' => now()->subDay(),
            'accesses_used' => 0,
            'activated_at' => now()->subDay(),
        ], $overrides));
    }

    private function accessesUsed(User $user, Service $service): int
    {
        return (int) DB::table('service_user')->where('user_id', $user->id)->where('service_id', $service->id)->value('accesses_used');
    }

    private function serviceData(int $organizationId, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organizationId,
            'name' => 'Serviciu',
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
