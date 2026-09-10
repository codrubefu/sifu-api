<?php

namespace Tests\Feature;

use App\Sms\Models\SmsMessage;
use App\Sms\Services\SmsPortalService;
use App\Service\Jobs\SendExpiringServiceSms;
use App\Service\Models\Service;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendExpiringServiceSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_and_stores_sms_for_service_expiring_at_configured_notice_days(): void
    {
        $this->travelTo('2026-06-01 10:00:00');
        config()->set('service.expiration_notice_days', 1);
        config()->set('services.smsportal.user', 'acomtws');
        config()->set('services.smsportal.password', 'secret');
        Http::fake(['https://mtws.smsportal.ro/main.aspx*' => Http::response('OK', 200)]);

        [$user, $service] = $this->attachService('2026-06-02');

        app(SendExpiringServiceSms::class)->handle(app(SmsPortalService::class));

        Http::assertSentCount(1);
        $this->assertDatabaseHas('sms_messages', [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'type' => SmsMessage::TYPE_SERVICE_EXPIRING,
            'destination' => '0722535723',
            'status' => SmsMessage::STATUS_SENT,
        ]);
    }

    public function test_it_does_not_send_same_expiration_sms_twice(): void
    {
        $this->travelTo('2026-06-01 10:00:00');
        config()->set('services.smsportal.user', 'acomtws');
        config()->set('services.smsportal.password', 'secret');
        Http::fake(['https://mtws.smsportal.ro/main.aspx*' => Http::response('OK', 200)]);

        $this->attachService('2026-06-02');

        $job = app(SendExpiringServiceSms::class);
        $job->handle(app(SmsPortalService::class));
        $job->handle(app(SmsPortalService::class));

        Http::assertSentCount(1);
        $this->assertDatabaseCount('sms_messages', 1);
    }

    public function test_it_uses_configured_notice_days(): void
    {
        $this->travelTo('2026-06-01 10:00:00');
        config()->set('service.expiration_notice_days', 3);
        config()->set('services.smsportal.user', 'acomtws');
        config()->set('services.smsportal.password', 'secret');
        Http::fake(['https://mtws.smsportal.ro/main.aspx*' => Http::response('OK', 200)]);

        $this->attachService('2026-06-02');
        $this->attachService('2026-06-04', '0733000000');

        app(SendExpiringServiceSms::class)->handle(app(SmsPortalService::class));

        Http::assertSentCount(1);
        $this->assertDatabaseHas('sms_messages', [
            'destination' => '0733000000',
            'status' => SmsMessage::STATUS_SENT,
        ]);
        $this->assertDatabaseMissing('sms_messages', [
            'destination' => '0722535723',
        ]);
    }

    private function attachService(string $expiresAt, string $phone = '0722535723'): array
    {
        $user = User::factory()->create(['phone' => $phone]);
        $service = Service::query()->create([
            'organization_id' => $user->organization_id,
            'name' => 'Gold',
            'description' => 'Gold service',
            'price' => 100,
            'currency' => 'RON',
            'duration_days' => 30,
            'max_users' => 10,
            'is_active' => true,
        ]);

        $user->services()->attach($service->id, [
            'start_date' => '2026-05-03',
            'expires_at' => $expiresAt,
        ]);

        return [$user, $service];
    }
}
