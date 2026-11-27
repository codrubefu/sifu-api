<?php

namespace Tests\Feature;

use App\Campaigns\Models\Campaign;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use App\Users\Services\BearerTokenService;
use Database\Seeders\ApplicationRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security regression (F-03): campaigns expose member contact data and send mass messages, so every
 * campaign route requires campaigns.view or campaigns.manage.
 */
class CampaignAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_without_campaign_rights_cannot_use_any_campaign_route(): void
    {
        $organization = Organization::factory()->create();
        $campaign = $this->campaign($organization);
        $token = $this->tokenWithRights($organization, ['profile.view']);
        $client = $this->withToken($token);

        $client->getJson('/api/campaigns')->assertForbidden();
        $client->postJson('/api/campaigns', ['name' => 'Spam', 'channel' => 'sms', 'content' => 'Hi'])->assertForbidden();
        $client->putJson("/api/campaigns/{$campaign->id}", ['content' => 'Changed'])->assertForbidden();
        $client->getJson("/api/campaigns/{$campaign->id}/preview")->assertForbidden();
        $client->postJson("/api/campaigns/{$campaign->id}/schedule", ['scheduled_at' => now()->addHour()->toIso8601String()])->assertForbidden();
        $client->postJson("/api/campaigns/{$campaign->id}/cancel")->assertForbidden();
        $client->getJson("/api/campaigns/{$campaign->id}/statistics")->assertForbidden();

        $this->assertSame(1, Campaign::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'status' => 'draft', 'content' => 'Original']);
    }

    public function test_user_without_any_explicit_right_cannot_preview_recipients(): void
    {
        $organization = Organization::factory()->create();
        $campaign = $this->campaign($organization);
        $member = User::factory()->create(['organization_id' => $organization->id]);

        $this->withToken(app(BearerTokenService::class)->create($member))
            ->getJson("/api/campaigns/{$campaign->id}/preview")
            ->assertForbidden();
    }

    public function test_view_right_can_read_but_not_write(): void
    {
        $organization = Organization::factory()->create();
        $campaign = $this->campaign($organization);
        $client = $this->withToken($this->tokenWithRights($organization, ['campaigns.view']));

        $client->getJson('/api/campaigns')->assertOk()->assertJsonPath('data.0.id', $campaign->id);
        $client->getJson("/api/campaigns/{$campaign->id}/preview")->assertOk();
        $client->getJson("/api/campaigns/{$campaign->id}/statistics")->assertOk();
        $client->postJson('/api/campaigns', ['name' => 'X', 'channel' => 'mail', 'content' => 'Hi'])->assertForbidden();
        $client->postJson("/api/campaigns/{$campaign->id}/schedule", ['scheduled_at' => now()->addHour()->toIso8601String()])->assertForbidden();
    }

    public function test_manage_right_can_create_and_schedule(): void
    {
        $organization = Organization::factory()->create();
        $client = $this->withToken($this->tokenWithRights($organization, ['campaigns.manage']));

        $id = $client->postJson('/api/campaigns', ['name' => 'Summer camp', 'channel' => 'mail', 'subject' => 'Camp', 'content' => 'Join us'])
            ->assertCreated()
            ->json('data.id');

        $client->postJson("/api/campaigns/{$id}/schedule", ['scheduled_at' => now()->addHour()->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');
    }

    public function test_campaign_rights_are_part_of_the_application_rights(): void
    {
        $names = ApplicationRights::definitions()->pluck('name')->all();

        $this->assertContains('campaigns.view', $names);
        $this->assertContains('campaigns.manage', $names);
    }

    public function test_migration_grants_campaign_rights_to_existing_user_and_report_managers_only(): void
    {
        $usersManagers = $this->groupWithRights('admins', ['users.manage']);
        $reportManagers = $this->groupWithRights('reporting', ['reports.manage']);
        $members = $this->groupWithRights('members', ['profile.view']);

        $migration = require database_path('migrations/2026_11_27_000002_add_campaign_rights.php');
        $migration->up();
        $migration->up(); // idempotent

        foreach ([$usersManagers, $reportManagers] as $group) {
            $this->assertEqualsCanonicalizing(
                ['campaigns.manage', 'campaigns.view'],
                $group->rights()->where('name', 'like', 'campaigns.%')->pluck('name')->all(),
            );
        }
        $this->assertFalse($members->rights()->where('name', 'like', 'campaigns.%')->exists());
        $this->assertSame(1, Right::query()->where('name', 'campaigns.view')->count());
    }

    private function campaign(Organization $organization): Campaign
    {
        return Campaign::query()->create([
            'organization_id' => $organization->id,
            'created_by' => User::factory()->create(['organization_id' => $organization->id])->id,
            'name' => 'Newsletter',
            'channel' => 'mail',
            'subject' => 'News',
            'content' => 'Original',
            'status' => 'draft',
        ]);
    }

    private function tokenWithRights(Organization $organization, array $rights): string
    {
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $group = $this->groupWithRights('g-'.$user->id, $rights, $organization->id);
        $user->groups()->attach($group);

        return app(BearerTokenService::class)->create($user);
    }

    private function groupWithRights(string $name, array $rights, ?int $organizationId = null): Group
    {
        $group = Group::query()->create(['name' => $name, 'label' => $name, 'organization_id' => $organizationId]);
        foreach ($rights as $right) {
            $group->rights()->attach(Right::query()->firstOrCreate(['name' => $right], ['label' => $right])->id);
        }

        return $group;
    }
}
