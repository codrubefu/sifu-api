<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CreateOrganizationAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_organization_with_administrator_user(): void
    {
        $exitCode = Artisan::call('create:organisation', [
            '--organization' => 'Acme SRL',
            '--slug' => 'acme',
            '--description' => 'Test organization',
            '--email' => 'admin@acme.test',
            '--first-name' => 'Ana',
            '--last-name' => 'Popescu',
            '--password' => 'password123',
        ]);

        $this->assertSame(0, $exitCode);

        $organization = Organization::query()->where('name', 'Acme SRL')->firstOrFail();
        $user = User::query()->where('email', 'admin@acme.test')->firstOrFail();
        $adminGroup = Group::query()
            ->where('organization_id', $organization->id)
            ->where('name', 'acme-srl-admin')
            ->firstOrFail();
        $userGroup = Group::query()
            ->where('organization_id', $organization->id)
            ->where('name', 'acme-srl-user')
            ->firstOrFail();

        $this->assertSame('Test organization', $organization->description);
        $this->assertSame('acme', $organization->slug);
        $this->assertSame($organization->id, $user->organization_id);
        $this->assertSame('Ana', $user->first_name);
        $this->assertSame('Popescu', $user->last_name);
        $this->assertTrue($user->groups()->whereKey($adminGroup->id)->exists());
        $this->assertTrue($adminGroup->rights()->where('name', 'users.manage')->exists());
        $this->assertTrue($adminGroup->rights()->where('name', 'custom-fields.manage')->exists());
        $this->assertSame(['profile.view'], $userGroup->rights()->pluck('name')->all());
    }

    public function test_command_persists_the_organization_url_option(): void
    {
        $exitCode = Artisan::call('create:organisation', [
            '--organization' => 'Url SRL',
            '--slug' => 'url-srl',
            '--url' => 'https://url-srl.example.com',
            '--description' => 'Test organization',
            '--email' => 'admin@url-srl.test',
            '--first-name' => 'Ana',
            '--last-name' => 'Popescu',
            '--password' => 'password123',
        ]);

        $this->assertSame(0, $exitCode);

        $organization = Organization::query()->where('name', 'Url SRL')->firstOrFail();
        $this->assertSame('https://url-srl.example.com', $organization->url);
    }

    public function test_command_can_prompt_for_organization_and_administrator_details(): void
    {
        $this->artisan('create:organisation')
            ->expectsQuestion('Organization name', 'Prompt SRL')
            ->expectsQuestion('Organization slug', 'prompt-custom')
            ->expectsQuestion('Organization description', 'Prompt organization')
            ->expectsQuestion('Administrator email', 'admin@prompt.test')
            ->expectsQuestion('Administrator first name', 'Maria')
            ->expectsQuestion('Administrator last name', 'Ionescu')
            ->expectsQuestion('Administrator password', 'password123')
            ->assertSuccessful();

        $organization = Organization::query()->where('name', 'Prompt SRL')->firstOrFail();
        $user = User::query()->where('email', 'admin@prompt.test')->firstOrFail();

        $this->assertSame('prompt-custom', $organization->slug);
        $this->assertSame($organization->id, $user->organization_id);
        $this->assertTrue($user->groups()->whereHas('rights', fn ($query) => $query->where('name', 'users.manage'))->exists());
    }
}
