<?php

namespace Tests\Feature;

use App\Events\Jobs\ExtendRecurringEventOccurrences;
use App\Events\Models\Event;
use App\Events\Services\EventOccurrenceGeneratorService;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use App\Users\Services\OrganizationSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrganizationSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function login(array $rights = ['organization_subscription.view', 'users.manage', 'groups.manage', 'locations.manage', 'events.manage', 'rights.manage']): array
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['organization_id' => $org->id, 'active' => true, 'password' => 'password']);
        $group = Group::create(['organization_id' => $org->id, 'name' => 'admin-'.$org->id, 'label' => 'Admin']);
        foreach ($rights as $name) {
            $group->rights()->syncWithoutDetaching([Right::firstOrCreate(['name' => $name], ['label' => $name])->id]);
        }
        $admin->groups()->attach($group);
        $token = $this->postJson('/api/login', ['organization_id' => $org->id, 'email' => $admin->email, 'password' => 'password'])->assertOk()->json('token');
        $this->withHeader('Authorization', 'Bearer '.$token);
        return [$org, $admin, $group];
    }

    private function override(Organization $org, string $resource, ?int $value): void
    {
        DB::table('organization_limit_overrides')->updateOrInsert(['organization_id' => $org->id, 'resource' => $resource], ['value' => $value, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_defaults_live_inheritance_overrides_and_read_authorization(): void
    {
        $this->getJson('/api/organization/subscription')->assertUnauthorized();
        [$org] = $this->login();
        $this->getJson('/api/organization/subscription')->assertOk()->assertJsonPath('data.plan.code', 'start')
            ->assertJsonPath('data.limits.administrators.used', 1)->assertJsonPath('data.limits.members.used', 0)
            ->assertJsonPath('data.limits.members.limit', 50);
        DB::table('organization_plans')->where('code', 'start')->update(['members' => 75]);
        $this->getJson('/api/organization/subscription')->assertJsonPath('data.limits.members.limit', 75);
        $this->override($org, 'members', null);
        $this->getJson('/api/organization/subscription')->assertJsonPath('data.limits.members.limit', null)->assertJsonPath('data.limits.members.source', 'override');
        $this->getJson('/api/organization/subscription?month=2026-13')->assertUnprocessable();
        $this->getJson('/api/organization/subscription?organization_id=999')->assertUnprocessable();
        $this->patchJson('/api/organization/subscription', ['plan' => 'pro'])->assertStatus(405);
    }

    public function test_read_right_is_required(): void
    {
        $this->login(['users.view']);
        $this->getJson('/api/organization/subscription')->assertForbidden();
        $this->postJson('/api/organization/subscription/check', ['resource' => 'members', 'quantity' => 1])->assertForbidden();
    }

    public function test_check_does_not_reserve_and_location_writes_enforce_limit(): void
    {
        [$org] = $this->login();
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/organization/subscription/check', ['resource' => 'locations', 'quantity' => 1])->assertOk()->assertJsonPath('data.allowed', true);
        }
        $location = $this->postJson('/api/locations', ['name' => 'Main'])->assertCreated()->json('data.id');
        $this->postJson('/api/locations', ['name' => 'Blocked'])->assertStatus(409)
            ->assertJsonPath('code', 'organization_limit_exceeded')->assertJsonPath('resource', 'locations')->assertJsonPath('projected', 2);
        $this->assertDatabaseMissing('locations', ['name' => 'Blocked']);
        $this->override($org, 'locations', 0);
        $this->patchJson('/api/locations/'.$location, ['name' => 'Renamed'])->assertOk();
        $this->getJson('/api/organization/subscription')->assertJsonPath('data.limits.locations.exceeded', true);
        $this->deleteJson('/api/locations/'.$location)->assertNoContent();
        $this->postJson('/api/organization/subscription/check', ['resource' => 'locations', 'quantity' => 1])->assertJsonPath('data.allowed', false);
        $this->postJson('/api/organization/subscription/check', ['resource' => 'events', 'quantity' => 1])->assertUnprocessable();
        $this->postJson('/api/organization/subscription/check', ['resource' => 'members', 'quantity' => 0])->assertUnprocessable();
    }

    public function test_member_and_admin_creation_reactivation_and_group_changes_are_atomic(): void
    {
        Mail::fake();
        [$org, , $adminGroup] = $this->login();
        $this->override($org, 'members', 1);
        $payload = ['first_name' => 'New', 'last_name' => 'Member', 'email' => 'one@example.test', 'active' => true];
        $member = $this->postJson('/api/clients', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/users', array_replace($payload, ['email' => 'two@example.test']))->assertStatus(409)->assertJsonPath('resource', 'members');
        $this->postJson('/api/administrators', array_replace($payload, ['email' => 'admin@example.test', 'group_ids' => [$adminGroup->id]]))
            ->assertStatus(409)->assertJsonPath('resource', 'administrators');
        $this->assertDatabaseMissing('users', ['email' => 'two@example.test']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
        // RefreshDatabase holds an outer transaction: deferred mail must remain unsent.
        Mail::assertNothingSent();
        $this->patchJson('/api/users/'.$member, ['group_ids' => [$adminGroup->id]])->assertStatus(409);
        $this->assertDatabaseMissing('group_user', ['group_id' => $adminGroup->id, 'user_id' => $member]);
        $this->patchJson('/api/users/'.$member, ['active' => false])->assertOk();
        $other = User::factory()->create(['organization_id' => $org->id, 'active' => true]);
        $this->patchJson('/api/users/'.$member, ['active' => true])->assertStatus(409)->assertJsonPath('resource', 'members');
        $this->assertFalse(User::withoutGlobalScopes()->find($member)->active);
        $group = Group::create(['organization_id' => $org->id, 'name' => 'member-group', 'label' => 'Members']);
        $other->groups()->attach($group);
        $right = Right::where('name', 'users.manage')->first();
        $this->patchJson('/api/groups/'.$group->id, ['right_ids' => [$right->id]])->assertStatus(409)->assertJsonPath('resource', 'administrators');
        $this->assertDatabaseMissing('group_right', ['group_id' => $group->id, 'right_id' => $right->id]);
    }

    public function test_counts_ignore_location_visibility_and_other_tenants(): void
    {
        [$org, $admin] = $this->login();
        $other = Organization::factory()->create();
        $location = Location::create(['organization_id' => $org->id, 'name' => 'Visible']);
        $admin->locations()->attach($location);
        Location::create(['organization_id' => $org->id, 'name' => 'Hidden']);
        Location::create(['organization_id' => $other->id, 'name' => 'Other']);
        User::factory()->create(['organization_id' => $org->id, 'active' => true]);
        User::factory()->count(2)->create(['organization_id' => $other->id, 'active' => true]);
        $this->getJson('/api/organization/subscription')->assertJsonPath('data.limits.members.used', 1)->assertJsonPath('data.limits.locations.used', 2);
    }

    public function test_cli_preserves_overrides_resets_and_audits(): void
    {
        $org = Organization::factory()->create();
        $this->artisan('organization:subscription:set', ['organization' => $org->id, '--members' => '200', '--administrators' => 'unlimited'])->assertSuccessful();
        $this->artisan('organization:subscription:set', ['organization' => $org->id, '--plan' => 'plus'])->assertSuccessful();
        $service = app(OrganizationSubscriptionService::class);
        $this->assertSame(200, $service->configuration($org->id)['limits']['members']['limit']);
        $this->assertNull($service->configuration($org->id)['limits']['administrators']['limit']);
        $this->artisan('organization:subscription:set', ['organization' => $org->id, '--reset' => ['members']])->assertSuccessful();
        $this->assertSame(150, $service->configuration($org->id)['limits']['members']['limit']);
        $this->artisan('organization:subscription:show', ['organization' => $org->id])->assertSuccessful();
        $this->artisan('organization:subscription:set', ['organization' => $org->id, '--members' => '-1'])->assertFailed();
        $this->artisan('organization:subscription:set', ['organization' => $org->id, '--plan' => 'missing'])->assertFailed();
        $this->artisan('organization:subscription:set', ['organization' => $org->id, '--reset-all' => true])->assertSuccessful();
        $this->assertSame(2, $service->configuration($org->id)['limits']['administrators']['limit']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $org->id, 'event_type' => 'organization.subscription.updated']);
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_replace(['title' => 'Weekly', 'start_time' => '10:00', 'end_time' => '11:00', 'start_date' => '2026-09-14',
            'recurrence_type' => 'weekly', 'recurrence_days' => ['monday'], 'status' => 'active'], $overrides);
    }

    public function test_event_creation_rolls_back_across_months_and_cancel_frees_capacity(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 11));
        [$org] = $this->login();
        $this->override($org, 'events', 3);
        $this->postJson('/api/events', $this->eventPayload())->assertStatus(409)->assertJsonPath('resource', 'events')->assertJsonPath('period', '2026-10');
        $this->assertDatabaseMissing('events', ['title' => 'Weekly']);
        $this->assertDatabaseCount('event_occurrences', 0);
        $this->override($org, 'events', 1);
        $id = $this->postJson('/api/events', $this->eventPayload(['recurrence_type' => 'once']))->assertCreated()->json('data.id');
        $occurrence = DB::table('event_occurrences')->where('event_id', $id)->first();
        $this->postJson('/api/events', $this->eventPayload(['recurrence_type' => 'once', 'start_date' => '2026-09-15']))->assertStatus(409);
        $this->patchJson('/api/event-occurrences/'.$occurrence->id.'/cancel')->assertOk();
        $this->postJson('/api/events', $this->eventPayload(['recurrence_type' => 'once', 'start_date' => '2026-09-15']))->assertCreated();
    }

    public function test_job_blocks_whole_extension_retries_and_deduplicates_blocks(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 11));
        [$org] = $this->login();
        $id = $this->postJson('/api/events', $this->eventPayload())->assertCreated()->json('data.id');
        $event = Event::findOrFail($id);
        $originalCount = $event->occurrences()->count();
        $this->override($org, 'events', 1);
        $this->travelTo(now()->setDate(2026, 10, 11));
        $job = new ExtendRecurringEventOccurrences();
        $generator = app(EventOccurrenceGeneratorService::class);
        $job->handle($generator);
        $this->assertSame($originalCount, $event->occurrences()->count());
        $this->assertSame('2026-11-11', $event->fresh()->occurrences_generated_until->toDateString());
        $this->assertDatabaseCount('organization_event_limit_blocks', 1);
        DB::table('personal_access_tokens')->update(['expires_at' => now()->addDay()]);
        $this->getJson('/api/organization/subscription')->assertOk()->assertJsonPath('data.event_generation_blocks.0.event_id', $id);
        $job->handle($generator);
        $this->assertDatabaseCount('organization_event_limit_blocks', 1);
        $this->override($org, 'events', null);
        $job->handle($generator);
        $this->assertDatabaseCount('organization_event_limit_blocks', 0);
        $this->assertSame('2026-12-11', $event->fresh()->occurrences_generated_until->toDateString());
        $count = $event->occurrences()->count();
        $job->handle($generator);
        $this->assertSame($count, $event->occurrences()->count());
    }
}
