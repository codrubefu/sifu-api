<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-26 12:00:00');
        [$this->admin, $this->token] = $this->authenticatedUserWithRights(['users.view']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_filter(): void
    {
        $this->member('on', ['active' => true]);
        $this->member('off', ['active' => false]);

        $this->assertListed(['active' => 1], ['on']);
        $this->assertListed(['active' => 0], ['off']);
    }

    public function test_has_active_service_follows_active_services_definition(): void
    {
        $service = $this->service();
        $graceService = $this->service(['grace_period_days' => 5]);
        $limited = $this->service(['max_accesses' => 3]);
        $inactiveService = $this->service(['is_active' => false]);
        $trashed = $this->service();
        $trashed->delete();

        $cases = [
            // name => [service, assignment overrides, expected active]
            'plain' => [$service, ['status' => 'active'], true],
            'noexpiry' => [$service, ['status' => 'active', 'expires_at' => null], true],
            'expired' => [$service, ['status' => 'active', 'expires_at' => '2026-11-25 12:00:00'], false],
            'ingrace' => [$graceService, ['status' => 'active', 'expires_at' => '2026-11-23 12:00:00'], true],
            'pastgrace' => [$graceService, ['status' => 'active', 'expires_at' => '2026-11-20 12:00:00'], false],
            'reservedstarted' => [$service, ['status' => 'reserved', 'start_date' => '2026-11-20 00:00:00'], true],
            'reservedfuture' => [$service, ['status' => 'reserved', 'start_date' => '2026-12-01 00:00:00'], false],
            'consumed' => [$limited, ['status' => 'active', 'accesses_used' => 3], false],
            'accessesleft' => [$limited, ['status' => 'active', 'accesses_used' => 2], true],
            'suspended' => [$service, ['status' => 'suspended', 'resume_at' => '2026-12-10 00:00:00'], false],
            'resumable' => [$service, ['status' => 'suspended', 'resume_at' => '2026-11-25 00:00:00'], true],
            'pending' => [$service, ['status' => 'pending'], false],
            'expiredstatus' => [$service, ['status' => 'expired'], false],
            'inactivesvc' => [$inactiveService, ['status' => 'active'], false],
            'trashedsvc' => [$trashed, ['status' => 'active'], false],
        ];

        $users = [];
        foreach ($cases as $name => [$caseService, $overrides]) {
            $users[$name] = $this->member($name);
            $this->assign($users[$name], $caseService, $overrides);
        }
        $this->member('none');

        $expectedActive = array_keys(array_filter($cases, fn (array $case): bool => $case[2]));
        $expectedInactive = array_merge(array_values(array_diff(array_keys($cases), $expectedActive)), ['none']);

        $this->assertListed(['has_active_service' => 1], $expectedActive);
        $this->assertListed(['has_active_service' => 0], $expectedInactive);

        // Same result as the refreshing activeServices() relation.
        foreach ($users as $name => $user) {
            $this->assertSame(
                in_array($name, $expectedActive, true),
                $user->fresh()->activeServices()->exists(),
                "activeServices() mismatch for {$name}",
            );
        }
    }

    public function test_service_id_filter_requires_active_assignment_of_that_service(): void
    {
        $karate = $this->service(['name' => 'Karate']);
        $judo = $this->service(['name' => 'Judo']);

        $this->assign($this->member('karate'), $karate);
        $this->assign($this->member('judo'), $judo);
        $this->assign($this->member('expiredkarate'), $karate, ['status' => 'expired']);

        $this->assertListed(['service_id' => $karate->id], ['karate']);
    }

    public function test_service_expiring_days_filter(): void
    {
        $service = $this->service();
        $this->assign($this->member('soon'), $service, ['expires_at' => '2026-11-30 12:00:00']);
        $this->assign($this->member('later'), $service, ['expires_at' => '2027-01-30 12:00:00']);
        $this->assign($this->member('never'), $service, ['expires_at' => null]);
        $this->assign($this->member('gone'), $service, ['expires_at' => '2026-11-28 12:00:00', 'status' => 'expired']);

        $this->assertListed(['service_expiring_days' => 7], ['soon']);
        $this->assertListed(['service_expiring_days' => 90], ['soon', 'later']);
    }

    public function test_grade_filter_uses_current_grade(): void
    {
        $white = $this->grade('White');
        $yellow = $this->grade('Yellow');

        $promoted = $this->member('promoted');
        $this->userGrade($promoted, $white, '2025-01-01');
        $this->userGrade($promoted, $yellow, '2026-06-01');
        $beginner = $this->member('beginner');
        $this->userGrade($beginner, $white, '2026-01-01');
        $this->member('nograde');

        $this->assertListed(['grade_id' => $white->id], ['beginner']);
        $this->assertListed(['grade_id' => $yellow->id], ['promoted']);
    }

    public function test_location_filter(): void
    {
        $hq = Location::query()->create(['name' => 'HQ', 'organization_id' => $this->admin->organization_id]);
        $other = Location::query()->create(['name' => 'Other', 'organization_id' => $this->admin->organization_id]);

        $this->member('athq')->locations()->attach($hq);
        $this->member('atother')->locations()->attach($other);
        $this->member('nowhere');

        $this->assertListed(['location_id' => $hq->id], ['athq']);
    }

    public function test_document_expired_filter(): void
    {
        $this->document($this->member('stale'), '2026-11-25');
        $this->document($this->member('fresh'), '2026-11-26');
        $this->document($this->member('replaced'), '2026-01-01', 'replaced');
        $this->document($this->member('nodate'), null);
        $this->member('nodocs');

        $this->assertListed(['document_expired' => 1], ['stale']);
        $this->assertListed(['document_expired' => 0], ['fresh', 'replaced', 'nodate', 'nodocs']);
    }

    public function test_filters_are_combined(): void
    {
        $service = $this->service();
        $hq = Location::query()->create(['name' => 'HQ', 'organization_id' => $this->admin->organization_id]);

        $match = $this->member('match');
        $match->locations()->attach($hq);
        $this->assign($match, $service, ['expires_at' => '2026-11-28 00:00:00']);

        $inactive = $this->member('inactive', ['active' => false]);
        $inactive->locations()->attach($hq);
        $this->assign($inactive, $service, ['expires_at' => '2026-11-28 00:00:00']);

        $elsewhere = $this->member('elsewhere');
        $this->assign($elsewhere, $service, ['expires_at' => '2026-11-28 00:00:00']);

        $this->assertListed([
            'active' => 1,
            'location_id' => $hq->id,
            'service_id' => $service->id,
            'service_expiring_days' => 7,
        ], ['match']);
    }

    public function test_sort_options(): void
    {
        $this->member('bravo', ['last_name' => 'Bravo', 'created_at' => '2026-01-01 00:00:00']);
        $this->member('alpha', ['last_name' => 'Alpha', 'created_at' => '2026-03-01 00:00:00']);
        $this->member('charlie', ['last_name' => 'Charlie', 'created_at' => '2026-02-01 00:00:00']);

        $this->assertListed([], ['alpha', 'bravo', 'charlie'], ordered: true);
        $this->assertListed(['sort' => 'name'], ['alpha', 'bravo', 'charlie'], ordered: true);
        $this->assertListed(['sort' => 'created_at'], ['bravo', 'charlie', 'alpha'], ordered: true);
        $this->assertListed(['sort' => '-created_at'], ['alpha', 'charlie', 'bravo'], ordered: true);
    }

    public function test_filters_apply_to_clients_and_administrators_lists(): void
    {
        $service = $this->service();
        $this->assign($this->member('client'), $service);
        $this->member('idle');

        $this->getJson('/api/clients?search=fltr-&has_active_service=1', $this->auth())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'fltr-client@example.com');

        $this->getJson('/api/administrators?search=fltr-&has_active_service=1', $this->auth())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_invalid_filter_values_return_422(): void
    {
        $invalid = [
            'active' => 'yes',
            'has_active_service' => '2',
            'service_id' => 999999,
            'service_expiring_days' => 0,
            'grade_id' => 'abc',
            'location_id' => 999999,
            'document_expired' => 'true',
            'sort' => 'email',
        ];

        foreach (['/api/users', '/api/clients', '/api/administrators'] as $path) {
            $this->getJson($path.'?'.http_build_query($invalid), $this->auth())
                ->assertUnprocessable()
                ->assertJsonValidationErrors(array_keys($invalid));
        }

        $this->getJson('/api/users?service_expiring_days=366', $this->auth())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_expiring_days']);
    }

    public function test_ids_from_another_organization_are_rejected(): void
    {
        $otherOrganization = Organization::factory()->create();
        $foreignService = Service::query()->withoutGlobalScopes()->create($this->serviceData([
            'organization_id' => $otherOrganization->id,
        ]));
        $foreignGrade = DB::table('grades')->insertGetId([
            'organization_id' => $otherOrganization->id,
            'name' => 'Foreign',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignLocation = DB::table('locations')->insertGetId([
            'organization_id' => $otherOrganization->id,
            'name' => 'Foreign',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/users?service_id='.$foreignService->id, $this->auth())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);
        $this->getJson('/api/users?grade_id='.$foreignGrade, $this->auth())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['grade_id']);
        $this->getJson('/api/users?location_id='.$foreignLocation, $this->auth())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_trashed_service_id_is_rejected(): void
    {
        $service = $this->service();
        $service->delete();

        $this->getJson('/api/users?service_id='.$service->id, $this->auth())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $expected
     */
    private function assertListed(array $filters, array $expected, bool $ordered = false): void
    {
        $emails = collect($this->getJson('/api/users?'.http_build_query(['search' => 'fltr-', 'per_page' => 100] + $filters), $this->auth())
            ->assertOk()
            ->json('data'))
            ->pluck('email')
            ->all();
        $expectedEmails = array_map(fn (string $name): string => "fltr-{$name}@example.com", $expected);

        if (! $ordered) {
            sort($emails);
            sort($expectedEmails);
        }

        $this->assertSame($expectedEmails, $emails, 'Filters: '.json_encode($filters));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(string $name, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'organization_id' => $this->admin->organization_id,
            'first_name' => ucfirst($name),
            'last_name' => 'Member',
            'email' => "fltr-{$name}@example.com",
        ], array_diff_key($attributes, ['created_at' => true])));

        if (array_key_exists('created_at', $attributes)) {
            DB::table('users')->where('id', $user->id)->update(['created_at' => $attributes['created_at']]);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function service(array $overrides = []): Service
    {
        return Service::query()->create($this->serviceData(array_merge([
            'organization_id' => $this->admin->organization_id,
        ], $overrides)));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function assign(User $user, Service $service, array $overrides = []): void
    {
        DB::table('service_user')->insert(array_merge([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'status' => 'active',
            'start_date' => '2026-11-01 00:00:00',
            'expires_at' => '2026-12-31 00:00:00',
            'accesses_used' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function grade(string $name): object
    {
        $id = DB::table('grades')->insertGetId([
            'organization_id' => $this->admin->organization_id,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['id' => $id];
    }

    private function userGrade(User $user, object $grade, string $obtainedAt): void
    {
        DB::table('user_grades')->insert([
            'organization_id' => $this->admin->organization_id,
            'user_id' => $user->id,
            'grade_id' => $grade->id,
            'obtained_at' => $obtainedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function document(User $user, ?string $expiresAt, string $status = 'active'): void
    {
        DB::table('user_documents')->insert([
            'organization_id' => $this->admin->organization_id,
            'user_id' => $user->id,
            'category' => 'medical',
            'title' => 'Medical certificate',
            'expires_at' => $expiresAt,
            'disk' => 'local',
            'path' => 'docs/'.$user->id.'.pdf',
            'original_name' => 'cert.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 100,
            'checksum' => str_repeat('a', 64),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function serviceData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Membership',
            'description' => 'Test service',
            'price' => 0,
            'currency' => 'EUR',
            'duration_days' => null,
            'max_users' => 25,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<int, string>  $rightNames
     * @return array{0: User, 1: string}
     */
    private function authenticatedUserWithRights(array $rightNames): array
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $group = Group::query()->create([
            'name' => fake()->unique()->slug(),
            'label' => 'Test Group',
        ]);

        foreach ($rightNames as $rightName) {
            $group->rights()->attach(Right::query()->firstOrCreate(['name' => $rightName], ['label' => $rightName]));
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
