<?php

namespace Tests\Feature;

use App\Users\Mail\PasswordSetupMail;
use App\Users\Models\Organization;
use App\Users\Models\PasswordSetupToken;
use App\Users\Models\SmtpSetting;
use App\Users\Models\User;
use App\Users\Services\OrganizationMailerService;
use App\Users\Services\PasswordSetupTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_email_for_existing_active_user(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'forgot@example.com', 'active' => true]);

        $this->postJson('/api/password/forgot', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
        ])->assertOk();

        Mail::assertQueued(PasswordSetupMail::class, function (PasswordSetupMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->isNewAccount === false;
        });
    }

    public function test_forgot_password_responds_the_same_way_for_unknown_email(): void
    {
        Mail::fake();

        $organization = Organization::query()->create(['name' => 'Org', 'slug' => 'org-forgot']);

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'unknown@example.com',
            'organization_id' => $organization->id,
        ])->assertOk();

        $known = $this->postJson('/api/password/forgot', [
            'email' => 'unknown@example.com',
            'organization_id' => $organization->id,
        ])->assertOk();

        $this->assertSame($response->json('message'), $known->json('message'));
        Mail::assertNothingQueued();
    }

    public function test_reset_password_with_valid_token_changes_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $token = $user->accessTokens()->create([
            'name' => 'api-token',
            'token' => hash('sha256', 'plain'),
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ]);

        $plainTextToken = app(PasswordSetupTokenService::class)->generate($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'token' => $plainTextToken,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'token' => 'not-a-real-token',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);
    }

    public function test_reset_password_fails_when_token_already_used(): void
    {
        $user = User::factory()->create();
        $plainTextToken = app(PasswordSetupTokenService::class)->generate($user);

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'token' => $plainTextToken,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->postJson('/api/password/reset', [
            'email' => $user->email,
            'organization_id' => $user->organization_id,
            'token' => $plainTextToken,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertStatus(422);
    }

    public function test_password_setup_mail_uses_organization_smtp_settings_when_configured(): void
    {
        Mail::fake();

        $organization = Organization::query()->create(['name' => 'SMTP Org', 'slug' => 'smtp-org']);
        SmtpSetting::query()->create([
            'organization_id' => $organization->id,
            'host' => 'smtp.custom-org.com',
            'port' => 2525,
            'from_address' => 'mail@smtp-org.example',
            'from_name' => 'SMTP Org',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $token = app(PasswordSetupTokenService::class)->generate($user);

        Mail::to($user->email)->queue(new PasswordSetupMail($user, $token, isNewAccount: false));

        Mail::assertQueued(PasswordSetupMail::class, function (PasswordSetupMail $mail) use ($organization) {
            $mail->build();

            return $mail->usesMailer('organization_'.$organization->id);
        });

        $this->assertSame(
            ['transport' => 'smtp', 'host' => 'smtp.custom-org.com', 'port' => 2525, 'encryption' => null, 'username' => null, 'password' => null],
            config('mail.mailers.organization_'.$organization->id),
        );
    }

    public function test_organization_mailer_service_falls_back_to_default_without_smtp_settings(): void
    {
        $organization = Organization::query()->create(['name' => 'No SMTP Org', 'slug' => 'no-smtp-org']);

        $this->assertNull(app(OrganizationMailerService::class)->mailerNameFor($organization));
    }

    public function test_password_setup_mail_links_to_the_organizations_own_url_when_set(): void
    {
        $organization = Organization::query()->create(['name' => 'Org With URL', 'slug' => 'org-with-url', 'url' => 'https://org-with-url.example.com']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $token = app(PasswordSetupTokenService::class)->generate($user);

        $mail = new PasswordSetupMail($user, $token, isNewAccount: false);

        $this->assertStringStartsWith('https://org-with-url.example.com/set-password?', $mail->link);
    }

    public function test_password_setup_mail_strips_any_path_from_the_organization_url(): void
    {
        $organization = Organization::query()->create(['name' => 'Org With Bad URL', 'slug' => 'org-with-bad-url', 'url' => 'http://localhost:5173/erp/members']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $token = app(PasswordSetupTokenService::class)->generate($user);

        $mail = new PasswordSetupMail($user, $token, isNewAccount: false);

        $this->assertStringStartsWith('http://localhost:5173/set-password?', $mail->link);
    }

    public function test_password_setup_mail_falls_back_to_frontend_url_when_organization_has_none(): void
    {
        $organization = Organization::query()->create(['name' => 'Org Without URL', 'slug' => 'org-without-url']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $token = app(PasswordSetupTokenService::class)->generate($user);

        $mail = new PasswordSetupMail($user, $token, isNewAccount: false);

        $this->assertStringStartsWith(rtrim((string) config('app.frontend_url'), '/').'/set-password?', $mail->link);
    }

    public function test_password_reset_tokens_are_isolated_per_organization_for_duplicate_emails(): void
    {
        $orgA = Organization::query()->create(['name' => 'Org A', 'slug' => 'org-a']);
        $orgB = Organization::query()->create(['name' => 'Org B', 'slug' => 'org-b']);

        $userA = User::factory()->create(['organization_id' => $orgA->id, 'email' => 'dup@example.com']);
        $userB = User::factory()->create(['organization_id' => $orgB->id, 'email' => 'dup@example.com']);

        $tokenForA = app(PasswordSetupTokenService::class)->generate($userA);

        $this->postJson('/api/password/reset', [
            'email' => 'dup@example.com',
            'organization_id' => $orgB->id,
            'token' => $tokenForA,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);

        $this->assertDatabaseHas('password_setup_tokens', [
            'id' => PasswordSetupToken::query()->where('user_id', $userA->id)->value('id'),
            'used_at' => null,
        ]);
    }
}
