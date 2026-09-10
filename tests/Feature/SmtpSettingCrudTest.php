<?php

namespace Tests\Feature;

use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\SmtpSetting;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpSettingCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_manage_right_can_create_smtp_settings(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['smtp_settings.manage']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/smtp-settings', [
                'host' => 'smtp.mailtrap.io',
                'port' => 587,
                'username' => 'mailer',
                'password' => 'secret',
                'encryption' => 'tls',
                'from_address' => 'no-reply@example.com',
                'from_name' => 'Example Org',
            ])
            ->assertCreated()
            ->assertJsonPath('data.host', 'smtp.mailtrap.io')
            ->assertJsonPath('data.has_password', true);

        $response->assertJsonMissingPath('data.password');
        $this->assertDatabaseHas('smtp_settings', ['host' => 'smtp.mailtrap.io', 'from_address' => 'no-reply@example.com']);
    }

    public function test_creating_smtp_settings_twice_for_same_organization_is_rejected(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['smtp_settings.manage']);
        SmtpSetting::query()->create($this->settingsPayload($admin->organization_id));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/smtp-settings', [
                'host' => 'smtp2.example.com',
                'port' => 587,
                'from_address' => 'other@example.com',
            ])
            ->assertStatus(422);
    }

    public function test_user_without_manage_right_cannot_create_smtp_settings(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['smtp_settings.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/smtp-settings', [
                'host' => 'smtp.mailtrap.io',
                'port' => 587,
                'from_address' => 'no-reply@example.com',
            ])
            ->assertForbidden();
    }

    public function test_user_with_view_right_can_show_smtp_settings(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['smtp_settings.view']);
        SmtpSetting::query()->create($this->settingsPayload($admin->organization_id));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/smtp-settings')
            ->assertOk()
            ->assertJsonPath('data.host', 'smtp.example.com');
    }

    public function test_show_returns_404_when_not_configured(): void
    {
        [, $token] = $this->authenticatedUserWithRights(['smtp_settings.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/smtp-settings')
            ->assertNotFound();
    }

    public function test_user_with_manage_right_can_update_smtp_settings(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['smtp_settings.manage']);
        SmtpSetting::query()->create($this->settingsPayload($admin->organization_id));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/smtp-settings', ['host' => 'smtp.updated.com'])
            ->assertOk()
            ->assertJsonPath('data.host', 'smtp.updated.com');

        $this->assertDatabaseHas('smtp_settings', ['organization_id' => $admin->organization_id, 'host' => 'smtp.updated.com']);
    }

    public function test_updating_with_blank_password_keeps_existing_password(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['smtp_settings.manage']);
        $setting = SmtpSetting::query()->create($this->settingsPayload($admin->organization_id, ['password' => 'original-secret']));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/smtp-settings', ['host' => 'smtp.updated.com', 'password' => ''])
            ->assertOk();

        $this->assertSame('original-secret', $setting->fresh()->password);
    }

    public function test_user_with_manage_right_can_delete_smtp_settings(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['smtp_settings.manage']);
        SmtpSetting::query()->create($this->settingsPayload($admin->organization_id));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/smtp-settings')
            ->assertNoContent();

        $this->assertDatabaseMissing('smtp_settings', ['organization_id' => $admin->organization_id]);
    }

    public function test_smtp_settings_are_isolated_per_organization(): void
    {
        $otherOrganization = Organization::query()->create(['name' => 'Other Org', 'slug' => 'other-org-smtp']);
        SmtpSetting::query()->create($this->settingsPayload($otherOrganization->id, ['host' => 'smtp.other-org.com']));

        [, $token] = $this->authenticatedUserWithRights(['smtp_settings.view']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/smtp-settings')
            ->assertNotFound();
    }

    private function settingsPayload(int $organizationId, array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $organizationId,
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer',
            'password' => 'secret',
            'encryption' => 'tls',
            'from_address' => 'no-reply@example.com',
            'from_name' => 'Example Org',
        ], $overrides);
    }

    private function authenticatedUserWithRights(array $rightNames): array
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

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
