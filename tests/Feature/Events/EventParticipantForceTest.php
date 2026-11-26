<?php

namespace Tests\Feature\Events;

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

/**
 * Occurrence quick add: listing members that miss the required services and
 * forcing them in with `checkins.override` (no access consumed, access_override marker).
 */
class EventParticipantForceTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_list_marks_eligibility_and_includes_ineligible_only_on_request(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.view']);
        [$monthly, $fram] = $this->services($admin->organization_id);
        $eligible = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($eligible, $monthly);
        $this->assign($eligible, $fram);
        $missingFram = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($missingFram, $monthly);
        $occurrence = $this->occurrence($this->event([[$monthly->id], [$fram->id]]));
        $url = "/api/event-occurrences/{$occurrence->id}/eligible-participants?per_page=50";

        $default = collect($this->withHeader('Authorization', "Bearer {$token}")->getJson($url)->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($default->has($eligible->id));
        $this->assertFalse($default->has($missingFram->id));
        $this->assertTrue($default[$eligible->id]['eligible']);
        $this->assertSame([], $default[$eligible->id]['missing_service_groups']);

        $all = collect($this->withHeader('Authorization', "Bearer {$token}")
            ->getJson($url.'&include_ineligible=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total'], 'links'])
            ->json('data'))->keyBy('id');
        $this->assertTrue($all[$eligible->id]['eligible']);
        $this->assertFalse($all[$missingFram->id]['eligible']);
        $this->assertSame([1], $all[$missingFram->id]['missing_service_groups']);
        $this->assertFalse($all[$admin->id]['eligible']);
        $this->assertSame([0, 1], $all[$admin->id]['missing_service_groups']);
    }

    public function test_force_without_override_right_is_forbidden(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        [$monthly] = $this->services($admin->organization_id);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $occurrence = $this->occurrence($this->event([[$monthly->id]]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $member->id, 'force' => true])
            ->assertForbidden()
            ->assertJsonPath('reason', 'override_not_allowed');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/bulk", ['user_ids' => [$member->id], 'force' => true])
            ->assertForbidden()
            ->assertJsonPath('reason', 'override_not_allowed');

        $this->assertDatabaseMissing('event_occurrence_user', ['event_occurrence_id' => $occurrence->id]);
    }

    public function test_forced_single_add_attaches_without_consumption_and_with_marker(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage', 'checkins.override']);
        [$monthly, $fram] = $this->services($admin->organization_id);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $monthly);
        $occurrence = $this->occurrence($this->event([[$monthly->id], [$fram->id]]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $member->id])
            ->assertForbidden()
            ->assertJsonPath('reason', 'missing_required_service');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $member->id, 'force' => true])
            ->assertCreated()
            ->assertJsonPath('forced', true)
            ->assertJsonPath('data.access_override', true);

        $this->assertDatabaseHas('event_occurrence_user', ['event_occurrence_id' => $occurrence->id, 'user_id' => $member->id, 'access_override' => true]);
        $this->assertSame(0, $this->accessesUsed($member, $monthly));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/event-occurrences/{$occurrence->id}/participants")
            ->assertOk()
            ->assertJsonPath('data.0.id', $member->id)
            ->assertJsonPath('data.0.access_override', true);
    }

    public function test_force_on_eligible_user_consumes_normally_without_marker(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage', 'checkins.override']);
        [$monthly] = $this->services($admin->organization_id);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $monthly);
        $occurrence = $this->occurrence($this->event([[$monthly->id]]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $member->id, 'force' => true])
            ->assertCreated()
            ->assertJsonPath('forced', false)
            ->assertJsonPath('data.access_override', false);

        $this->assertSame(1, $this->accessesUsed($member, $monthly));
    }

    public function test_forced_bulk_add_mixes_consuming_and_forced_users(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage', 'checkins.override']);
        [$monthly] = $this->services($admin->organization_id);
        $eligible = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($eligible, $monthly);
        $ineligible = User::factory()->create(['organization_id' => $admin->organization_id]);
        $occurrence = $this->occurrence($this->event([[$monthly->id]]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/bulk", ['user_ids' => [$eligible->id, $ineligible->id]])
            ->assertForbidden();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/bulk", ['user_ids' => [$eligible->id, $ineligible->id], 'force' => true])
            ->assertCreated()
            ->assertJsonPath('forced_user_ids', [$ineligible->id]);

        $markers = collect($response->json('data'))->pluck('access_override', 'id');
        $this->assertFalse($markers[$eligible->id]);
        $this->assertTrue($markers[$ineligible->id]);
        $this->assertSame(1, $this->accessesUsed($eligible, $monthly));
        $this->assertDatabaseHas('event_occurrence_user', ['event_occurrence_id' => $occurrence->id, 'user_id' => $ineligible->id, 'access_override' => true]);
        $this->assertDatabaseHas('event_occurrence_user', ['event_occurrence_id' => $occurrence->id, 'user_id' => $eligible->id, 'access_override' => false]);
    }

    public function test_bulk_without_force_returns_empty_forced_user_ids(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage']);
        [$monthly] = $this->services($admin->organization_id);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $this->assign($member, $monthly);
        $occurrence = $this->occurrence($this->event([[$monthly->id]]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/bulk", ['user_ids' => [$member->id]])
            ->assertCreated()
            ->assertJsonPath('forced_user_ids', []);
    }

    public function test_capacity_still_blocks_forced_adds(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage', 'checkins.override']);
        [$monthly] = $this->services($admin->organization_id);
        $occurrence = $this->occurrence($this->event([[$monthly->id]], ['max_participants' => 1]));
        $first = User::factory()->create(['organization_id' => $admin->organization_id]);
        $second = User::factory()->create(['organization_id' => $admin->organization_id]);
        $third = User::factory()->create(['organization_id' => $admin->organization_id]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $first->id, 'force' => true])
            ->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $second->id, 'force' => true])
            ->assertStatus(400);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants/bulk", ['user_ids' => [$second->id, $third->id], 'force' => true])
            ->assertStatus(400);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$occurrence->id}/participants", ['user_id' => $first->id, 'force' => true])
            ->assertUnprocessable();

        $this->assertSame(1, DB::table('event_occurrence_user')->where('event_occurrence_id', $occurrence->id)->count());
    }

    public function test_force_is_not_applied_to_future_occurrences(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['event_participants.manage', 'checkins.override']);
        [$monthly] = $this->services($admin->organization_id);
        $member = User::factory()->create(['organization_id' => $admin->organization_id]);
        $event = $this->event([[$monthly->id]]);
        $target = $this->occurrence($event, now()->addDay()->toDateString());
        $future = $this->occurrence($event, now()->addDays(8)->toDateString());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/event-occurrences/{$target->id}/participants/bulk", [
                'user_ids' => [$member->id],
                'force' => true,
                'apply_to_future_occurrences' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('forced_user_ids', [$member->id])
            ->assertJsonPath('future_occurrences_updated.0.occurrence_id', $future->id)
            ->assertJsonPath('future_occurrences_updated.0.added_user_ids', [])
            ->assertJsonPath('future_occurrences_updated.0.skipped.0.reason', 'missing_required_service');

        $this->assertDatabaseMissing('event_occurrence_user', ['event_occurrence_id' => $future->id, 'user_id' => $member->id]);
    }

    private function event(array $serviceGroups, array $overrides = []): Event
    {
        $event = Event::query()->create(array_merge([
            'title' => 'Antrenament',
            'location' => 'Sala 1',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'recurrence_type' => 'once',
            'start_date' => now()->addDay()->toDateString(),
            'requires_active_service' => true,
            'requires_payment' => false,
            'max_participants' => null,
            'status' => 'active',
        ], $overrides));
        $event->syncServiceGroups($serviceGroups);

        return $event;
    }

    private function occurrence(Event $event, ?string $date = null): EventOccurrence
    {
        $date ??= now()->addDay()->toDateString();

        return EventOccurrence::query()->create([
            'event_id' => $event->id,
            'occurrence_date' => $date,
            'start_datetime' => "{$date} 10:00:00",
            'end_datetime' => "{$date} 11:00:00",
            'status' => 'scheduled',
        ]);
    }

    /**
     * @return array{0: Service, 1: Service}
     */
    private function services(int $organizationId): array
    {
        return [
            Service::query()->create($this->serviceData($organizationId, ['name' => 'Taxa lunara', 'max_accesses' => 5])),
            Service::query()->create($this->serviceData($organizationId, ['name' => 'Taxa FRAM', 'max_accesses' => 10])),
        ];
    }

    private function assign(User $user, Service $service): void
    {
        $user->services()->attach($service->id, [
            'status' => 'active',
            'start_date' => now()->subDay(),
            'accesses_used' => 0,
            'activated_at' => now()->subDay(),
        ]);
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
