<?php

namespace Tests\Feature;

use App\Notifications\Events\NotificationRequested;
use App\Notifications\Jobs\SendNotificationDelivery;
use App\Notifications\Models\NotificationDelivery;
use App\Notifications\Services\NotificationSender;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class EventNotificationLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_event_is_idempotent_and_only_queues_consented_channels(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'phone' => '0712345678',
            'notification_consents' => ['sms' => true, 'mail' => false, 'push' => false],
        ]);

        NotificationRequested::dispatch($user, NotificationRequested::SERVICE_ACTIVATED, 'activation:42', ['service' => 'Anuală']);
        NotificationRequested::dispatch($user, NotificationRequested::SERVICE_ACTIVATED, 'activation:42', ['service' => 'Anuală']);

        $this->assertDatabaseCount('notification_deliveries', 1);
        $this->assertDatabaseHas('notification_deliveries', ['event_key' => 'activation:42', 'channel' => 'sms']);
        Queue::assertPushed(SendNotificationDelivery::class, 1);
    }

    public function test_a_failed_delivery_is_recorded_and_can_be_retried(): void
    {
        $user = User::factory()->create();
        $delivery = NotificationDelivery::query()->create([
            'user_id' => $user->id, 'event_type' => NotificationRequested::URGENT_ANNOUNCEMENT,
            'event_key' => 'urgent:9', 'channel' => 'mail', 'template' => NotificationRequested::URGENT_ANNOUNCEMENT,
            'payload' => ['message' => 'Test'], 'status' => 'pending',
        ]);
        $sender = $this->mock(NotificationSender::class);
        $sender->shouldReceive('send')->once()->andThrow(new RuntimeException('temporary outage'));

        try { (new SendNotificationDelivery($delivery->id))->handle($sender); } catch (RuntimeException) {}

        $sender->shouldReceive('send')->once()->andReturn(['provider' => 'array', 'external_id' => 'mail-123']);
        (new SendNotificationDelivery($delivery->id))->handle($sender);

        $this->assertDatabaseHas('notification_attempts', ['notification_delivery_id' => $delivery->id, 'attempt' => 1, 'status' => 'failed', 'error' => 'temporary outage']);
        $this->assertDatabaseHas('notification_attempts', ['notification_delivery_id' => $delivery->id, 'attempt' => 2, 'status' => 'sent', 'external_id' => 'mail-123']);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'sent']);
    }
}
