<?php

namespace Tests\Feature;

use App\Events\Models\Event;
use App\Events\Models\EventOccurrence;
use App\Notifications\Models\EmailTemplate;
use App\Notifications\Models\NotificationDelivery;
use App\Notifications\Services\EmailTemplateService;
use App\Notifications\Services\NotificationSender;
use App\Payments\Services\PaymentService;
use App\Users\Mail\PasswordSetupMail;
use App\Users\Models\Group;
use App\Users\Models\Organization;
use App\Users\Models\Right;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_is_tenant_isolated_and_validates_templates(): void
    {
        [$admin, $token] = $this->authenticatedUserWithRights(['email_templates.manage']);
        $this->withHeader('Authorization', "Bearer {$token}");
        $payload = ['type' => 'account.created', 'subject' => 'Salut {{first_name}}', 'body' => 'Parola: {{setup_url}}'];
        $id = $this->postJson('/api/email-templates', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/email-templates', $payload)->assertUnprocessable();
        $this->getJson('/api/email-templates')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/email-templates/types')->assertOk()->assertJsonCount(3, 'data');
        $this->patchJson("/api/email-templates/{$id}", ['body' => 'Missing link'])->assertUnprocessable();
        $this->patchJson("/api/email-templates/{$id}", ['subject' => '{{unknown}}'])->assertUnprocessable();
        $this->patchJson("/api/email-templates/{$id}", ['subject' => 'Welcome'])->assertOk()->assertJsonPath('data.subject', 'Welcome');
        $other = EmailTemplate::create(['organization_id' => Organization::factory()->create()->id, 'type' => 'account.created', 'subject' => 'Other', 'body' => '{{setup_url}}']);
        $this->getJson("/api/email-templates/{$other->id}")->assertNotFound();
        $this->patchJson("/api/email-templates/{$other->id}", ['subject' => 'Changed'])->assertNotFound();
        $this->deleteJson("/api/email-templates/{$other->id}")->assertNotFound();
        $this->deleteJson("/api/email-templates/{$id}")->assertNoContent();
        $this->assertSame('Bine ai venit la '.$admin->organization->name.'!', app(EmailTemplateService::class)->render($admin, 'account.created')['subject']);
    }

    public function test_rights_are_required(): void
    {
        $this->getJson('/api/email-templates')->assertUnauthorized();
        [, $token] = $this->authenticatedUserWithRights(['email_templates.view']);
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/email-templates', [])->assertForbidden();
    }

    public function test_account_email_uses_safe_organization_template_and_reset_is_unchanged(): void
    {
        $user = User::factory()->create(['first_name' => '<script>alert(1)</script>']);
        EmailTemplate::create(['organization_id' => $user->organization_id, 'type' => 'account.created', 'subject' => 'Welcome {{first_name}}', 'body' => '{{first_name}} {{setup_url}}']);
        $mail = new PasswordSetupMail($user, 'test-token');
        $html = $mail->render();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('test-token', $html);
        $reset = (new PasswordSetupMail($user, 'reset-token', false))->build();
        $this->assertSame('Resetare parolă', $reset->subject);
    }

    public function test_payment_email_is_confirmed_once_and_has_pdf_attachment(): void
    {
        Queue::fake();
        $user = User::factory()->create(['notification_consents' => ['mail' => true]]);
        $serviceId = DB::table('services')->insertGetId(['organization_id' => $user->organization_id, 'name' => 'Karate', 'price' => 100, 'currency' => 'RON', 'duration_days' => 30, 'is_active' => true]);
        $assignmentId = DB::table('service_user')->insertGetId(['service_id' => $serviceId, 'user_id' => $user->id]);
        $service = app(PaymentService::class);
        $payment = $service->create(['first_name' => 'Ana', 'last_name' => 'Pop', 'payment_type_id' => 3, 'model_id' => $assignmentId, 'amount' => 100, 'paid_at' => now()], $user);
        $this->assertDatabaseMissing('notification_deliveries', ['template' => 'payment.confirmed']);
        $callback = ['external_reference' => $payment->external_reference, 'status' => 'confirmed'];
        $service->processCallback($callback);
        $service->processCallback($callback);
        $delivery = NotificationDelivery::where('template', 'payment.confirmed')->sole();
        $this->assertSame('mail', $delivery->channel);
        app(NotificationSender::class)->send($delivery);
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertCount(1, $message->getAttachments());
        $this->assertStringStartsWith('%PDF-', $message->getAttachments()[0]->getBody());
        $this->assertStringContainsString($payment->fresh()->receipt_number, $message->getSubject());
    }

    public function test_participant_notifications_only_follow_committed_registrations_and_mail_consent(): void
    {
        Queue::fake();
        $user = User::factory()->create(['notification_consents' => ['mail' => true, 'sms' => true]]);
        $event = Event::create(['organization_id' => $user->organization_id, 'title' => 'Karate', 'start_time' => '10:00', 'end_time' => '11:00', 'recurrence_type' => 'none', 'start_date' => '2026-09-14', 'status' => 'active']);
        $occurrence = EventOccurrence::create(['organization_id' => $user->organization_id, 'event_id' => $event->id, 'occurrence_date' => '2026-09-14', 'start_datetime' => '2026-09-14 10:00', 'end_datetime' => '2026-09-14 11:00', 'status' => 'scheduled']);
        DB::beginTransaction();
        $occurrence->participants()->attach($user->id, ['status' => 'registered']);
        $this->assertDatabaseCount('notification_deliveries', 0);
        DB::rollBack();
        $this->assertDatabaseCount('notification_deliveries', 0);
        DB::transaction(fn () => $occurrence->participants()->attach($user->id, ['status' => 'registered']));
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'template' => 'event.attached', 'channel' => 'mail']);
        $this->assertDatabaseCount('notification_deliveries', 1);
        $noConsent = User::factory()->create(['organization_id' => $user->organization_id, 'notification_consents' => ['mail' => false]]);
        $occurrence->participants()->attach($noConsent->id, ['status' => 'registered']);
        $this->assertDatabaseCount('notification_deliveries', 1);
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
