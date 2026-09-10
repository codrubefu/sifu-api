<?php

namespace Tests\Feature;

use App\Payments\Models\Payment;
use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_dashboard_or_report_right_cannot_view_dashboard(): void
    {
        [, $token] = $this->loginWith('profile.view');

        $this->withToken($token)->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_dashboard_returns_tenant_safe_aggregates(): void
    {
        [$operator, $token] = $this->loginWith('dashboard.view');
        $location = Location::query()->create(['organization_id' => $operator->organization_id, 'name' => 'HQ']);
        $operator->locations()->attach($location->id);
        $service = Service::query()->create([
            'organization_id' => $operator->organization_id,
            'name' => 'Monthly',
            'price' => 100,
            'currency' => 'RON',
            'duration_days' => 30,
            'is_active' => true,
        ]);
        $operator->services()->attach($service->id, [
            'status' => 'suspended',
            'start_date' => now()->subDays(10),
            'expires_at' => now()->addDays(5),
        ]);
        Payment::query()->create([
            'organization_id' => $operator->organization_id,
            'first_name' => 'Ana',
            'last_name' => 'Pop',
            'payment_type_id' => Payment::TYPE_CARD,
            'status' => Payment::STATUS_CONFIRMED,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'model_id' => 1,
            'amount' => 100,
            'paid_at' => '2026-07-10',
            'admin_id' => $operator->id,
        ]);
        $outsider = User::factory()->create();
        Payment::query()->create([
            'organization_id' => $outsider->organization_id,
            'first_name' => 'Other',
            'last_name' => 'Org',
            'payment_type_id' => Payment::TYPE_CARD,
            'status' => Payment::STATUS_CONFIRMED,
            'model_type' => Payment::MODEL_TYPE_SERVICE_USER,
            'amount' => 999,
            'paid_at' => '2026-07-10',
            'admin_id' => $outsider->id,
        ]);

        $this->withToken($token)->getJson('/api/dashboard?from=2026-07-01&to=2026-07-31&group_by=month')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'filters',
                    'stats' => ['active_members', 'flagged_services', 'total_revenue', 'active_locations'],
                    'revenue_by_period',
                    'member_status',
                    'activity',
                    'automations',
                ],
            ])
            ->assertJsonPath('data.stats.total_revenue', 100)
            ->assertJsonPath('data.revenue_by_period.0.period', '2026-07')
            ->assertJsonPath('data.revenue_by_period.0.revenue', 100);
    }

    private function loginWith(string $rightName): array
    {
        $user = User::factory()->create(['password' => 'password']);
        $right = Right::query()->create(['name' => $rightName, 'label' => $rightName]);
        $group = Group::query()->create(['name' => fake()->unique()->slug(), 'label' => 'Dashboard']);
        $group->rights()->attach($right);
        $user->groups()->attach($group);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'organization_id' => $user->organization_id, 'password' => 'password'])->json('token');

        return [$user, $token];
    }
}
