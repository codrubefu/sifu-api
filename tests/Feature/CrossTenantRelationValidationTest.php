<?php

namespace Tests\Feature;

use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\LocationGroup;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use App\Users\Services\BearerTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Security regression (F-04): `exists:table,id` ignores tenant scopes and pivot `sync()` writes rows
 * directly, so ids coming from the request must be validated against the authenticated organization.
 */
class CrossTenantRelationValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $own;

    private Organization $other;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->own = Organization::factory()->create();
        $this->other = Organization::factory()->create();
        $this->token = $this->adminToken($this->own);
    }

    public function test_location_cannot_be_linked_to_another_organizations_users_or_location_group(): void
    {
        $foreignUser = User::factory()->create(['organization_id' => $this->other->id]);
        $foreignGroup = LocationGroup::query()->create(['name' => 'Foreign', 'organization_id' => $this->other->id]);
        $ownLocation = Location::query()->create(['name' => 'Own hall', 'organization_id' => $this->own->id]);

        $client = $this->withToken($this->token);
        $client->postJson('/api/locations', ['name' => 'New hall', 'user_ids' => [$foreignUser->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('user_ids.0');
        $client->putJson("/api/locations/{$ownLocation->id}", ['user_ids' => [$foreignUser->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('user_ids.0');
        $client->postJson('/api/locations', ['name' => 'Other hall', 'location_group_id' => $foreignGroup->id])
            ->assertUnprocessable()->assertJsonValidationErrors('location_group_id');

        $this->assertSame(0, DB::table('location_user')->where('user_id', $foreignUser->id)->count());
        $this->assertDatabaseMissing('locations', ['name' => 'Other hall']);
    }

    public function test_service_cannot_be_assigned_to_another_organizations_users(): void
    {
        $foreignUser = User::factory()->create(['organization_id' => $this->other->id]);
        $ownService = $this->service($this->own);

        $client = $this->withToken($this->token);
        $client->postJson('/api/services', ['name' => 'Pass', 'price' => 0, 'currency' => 'RON', 'user_ids' => [$foreignUser->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('user_ids.0');
        $client->putJson("/api/services/{$ownService->id}", ['user_ids' => [$foreignUser->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('user_ids.0');

        $this->assertSame(0, DB::table('service_user')->where('user_id', $foreignUser->id)->count());
    }

    public function test_user_cannot_be_linked_to_another_organizations_locations_or_services(): void
    {
        $member = User::factory()->create(['organization_id' => $this->own->id]);
        $foreignLocation = Location::query()->create(['name' => 'Foreign hall', 'organization_id' => $this->other->id]);
        $foreignService = $this->service($this->other);

        $client = $this->withToken($this->token);
        $client->patchJson("/api/users/{$member->id}", ['location_ids' => [$foreignLocation->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('location_ids.0');
        $client->patchJson("/api/users/{$member->id}", ['service_ids' => [$foreignService->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('service_ids.0');
        $client->patchJson("/api/users/service/{$member->id}", ['service_ids' => [$foreignService->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('service_ids.0');
        $client->postJson('/api/users', [
            'first_name' => 'Ana',
            'last_name' => 'Pop',
            'email' => 'ana@example.test',
            'location_ids' => [$foreignLocation->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('location_ids.0');

        $this->assertSame(0, DB::table('location_user')->where('location_id', $foreignLocation->id)->count());
        $this->assertSame(0, DB::table('service_user')->where('service_id', $foreignService->id)->count());
    }

    public function test_own_and_shared_ids_are_still_accepted(): void
    {
        $member = User::factory()->create(['organization_id' => $this->own->id]);
        $ownLocation = Location::query()->create(['name' => 'Own hall', 'organization_id' => $this->own->id]);
        $otherOwnLocation = Location::query()->create(['name' => 'Second hall', 'organization_id' => $this->own->id]);
        $sharedLocation = Location::query()->create(['name' => 'Shared hall', 'organization_id' => null]);

        $client = $this->withToken($this->token);
        $client->patchJson("/api/users/{$member->id}", ['location_ids' => [$ownLocation->id, $sharedLocation->id]])->assertOk();
        $client->postJson('/api/services', ['name' => 'Pass', 'price' => 0, 'currency' => 'RON', 'user_ids' => [$member->id]])->assertCreated();
        $client->putJson("/api/locations/{$otherOwnLocation->id}", ['user_ids' => [$member->id]])->assertOk();

        $this->assertSame(3, DB::table('location_user')->where('user_id', $member->id)->count());
        $this->assertSame(1, DB::table('service_user')->where('user_id', $member->id)->count());
    }

    /**
     * Guard against reintroducing unscoped `exists:` rules on tenant-owned tables. Each allowed entry is
     * safe for the stated reason; anything new must be scoped or justified here.
     */
    public function test_no_new_unscoped_exists_rules_on_tenant_tables(): void
    {
        $allowed = [
            // Global tables.
            'exists:rights,id', 'exists:organizations,id', 'exists:organization_plans,code',
            // Controllers re-resolve through tenant-scoped models (findOrFail) before using the id.
            'app/CheckIns/Http/Requests/ConfirmCheckInRequest.php',
            'app/CheckIns/Http/Requests/SearchCheckInMemberRequest.php',
            'app/Events/Http/Requests/AddEventParticipantRequest.php',
            'app/Events/Http/Requests/BulkAddEventParticipantsRequest.php',
            // ServiceLifecycleService::activate rejects payments of another organization.
            'app/Service/Http/Controllers/Api/ServiceController.php',
        ];

        $violations = [];
        foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
            $relative = 'app/'.str_replace('\\', '/', $file->getRelativePathname());
            if (in_array($relative, $allowed, true)) {
                continue;
            }
            preg_match_all("/'exists:[a-z_]+,[a-z_]+'/", $file->getContents(), $matches);
            foreach (array_diff($matches[0], array_map(fn ($rule) => "'{$rule}'", $allowed)) as $rule) {
                $violations[] = "{$relative}: {$rule}";
            }
        }

        $this->assertSame([], $violations, 'Use OrganizationScopedExistsRule for tenant-owned tables.');
    }

    private function adminToken(Organization $organization): string
    {
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $group = Group::query()->create(['name' => 'admins-'.$organization->id, 'label' => 'Admins', 'organization_id' => $organization->id]);
        foreach (['locations.manage', 'services.manage', 'users.manage'] as $name) {
            $group->rights()->attach(Right::query()->firstOrCreate(['name' => $name], ['label' => $name])->id);
        }
        $admin->groups()->attach($group);

        return app(BearerTokenService::class)->create($admin);
    }

    private function service(Organization $organization): Service
    {
        return Service::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pass '.$organization->id,
            'price' => 0,
            'currency' => 'RON',
            'duration_days' => 30,
            'expiration_rule' => 'duration',
            'grace_period_days' => 0,
            'is_active' => true,
        ]);
    }
}
