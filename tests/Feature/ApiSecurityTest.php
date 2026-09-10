<?php

namespace Tests\Feature;

use App\Users\Models\Organization;
use App\Users\Models\User;
use App\Users\Services\BearerTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_has_a_distinct_composite_rate_limit(): void
    {
        config(['security.rate_limits.login_per_minute' => 2]);
        $organization = Organization::query()->create(['name' => 'Secure', 'slug' => 'secure']);
        $payload = ['email' => 'declared@example.test', 'organization_id' => $organization->id, 'password' => 'invalid'];

        $this->postJson('/api/login', $payload)->assertUnauthorized();
        $this->postJson('/api/login', $payload)->assertUnauthorized();
        $this->postJson('/api/login', $payload)->assertTooManyRequests();

        $payload['email'] = 'another@example.test';
        $this->postJson('/api/login', $payload)->assertUnauthorized();
    }

    public function test_tokens_expire_at_the_configured_time(): void
    {
        Carbon::setTestNow('2026-08-09 10:00:00');
        config(['security.tokens.expiration_minutes' => 15]);
        $user = User::factory()->create();
        $service = app(BearerTokenService::class);
        $plainToken = $service->create($user);

        $this->assertTrue($user->accessTokens()->first()->expires_at->equalTo(now()->addMinutes(15)));
        Carbon::setTestNow(now()->addMinutes(16));
        $this->assertNull($service->findValidToken($plainToken));
    }

    public function test_disabled_user_cannot_use_an_existing_token(): void
    {
        $user = User::factory()->create(['active' => true]);
        $token = app(BearerTokenService::class)->create($user);
        $user->update(['active' => false]);

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_password_change_revokes_all_sessions(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $service = app(BearerTokenService::class);
        $first = $service->create($user);
        $second = $service->create($user);

        $user->update(['password' => 'new-password']);

        $this->assertNull($service->findValidToken($first));
        $this->assertNull($service->findValidToken($second));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
