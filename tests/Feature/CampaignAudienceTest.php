<?php

namespace Tests\Feature;

use App\Articles\Models\Article;
use App\Campaigns\Models\Campaign;
use App\Campaigns\Services\CampaignService;
use App\Notifications\Jobs\SendNotificationDelivery;
use App\Notifications\Models\NotificationDelivery;
use App\Notifications\Models\NotificationPreference;
use App\Notifications\Services\NotificationSender;
use App\Reporting\Models\Segment;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_article_segments_are_tenant_safe(): void
    {
        $user = User::factory()->create(['active' => true]);
        $other = User::factory()->create(['active' => true]);
        Auth::setUser($user);
        $segment = Segment::query()->create(['organization_id' => $user->organization_id, 'created_by' => $user->id, 'name' => 'Active', 'criteria' => ['active' => true]]);
        $foreign = Segment::withoutGlobalScopes()->create(['organization_id' => $other->organization_id, 'created_by' => $other->id, 'name' => 'Foreign', 'criteria' => ['active' => true]]);
        $article = $this->article($user, $segment->id);
        $foreignArticle = $this->article($user, $foreign->id);

        $this->assertTrue(Article::query()->visibleTo($user)->whereKey($article)->exists());
        $this->assertFalse(Article::query()->visibleTo($user)->whereKey($foreignArticle)->exists());
        $user->update(['active' => false]);
        $this->assertFalse(Article::query()->visibleTo($user->fresh())->whereKey($article)->exists());
    }

    public function test_eligibility_is_recomputed_and_dispatch_is_idempotent(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        Auth::setUser($owner);
        $recipient = User::factory()->create(['organization_id' => $owner->organization_id, 'active' => false]);
        $segment = Segment::query()->create(['organization_id' => $owner->organization_id, 'created_by' => $owner->id, 'name' => 'Active', 'criteria' => ['active' => true]]);
        $campaign = $this->campaign($owner, $segment);
        $recipient->update(['active' => true]);

        $service = app(CampaignService::class);
        $this->assertSame(2, $service->dispatch($campaign)); // owner and newly eligible recipient
        $this->assertSame(0, $service->dispatch($campaign->fresh()));
        $this->assertDatabaseCount('notification_deliveries', 2);
    }

    public function test_opt_out_is_checked_again_at_delivery_time(): void
    {
        $user = User::factory()->create(['notification_consents' => ['mail' => true]]);
        $delivery = NotificationDelivery::query()->create(['user_id' => $user->id, 'event_type' => 'campaign', 'event_key' => 'campaign:1', 'channel' => 'mail', 'template' => 'campaign', 'payload' => ['message' => 'Hello'], 'consent_scope' => 'campaigns']);
        NotificationPreference::query()->create(['user_id' => $user->id, 'channel' => 'mail', 'scope' => 'campaigns', 'subscribed' => false]);
        $sender = $this->mock(NotificationSender::class);
        $sender->shouldNotReceive('send');

        (new SendNotificationDelivery($delivery->id))->handle($sender);

        $this->assertDatabaseHas('notification_deliveries', ['id' => $delivery->id, 'status' => 'skipped', 'skip_reason' => 'consent']);
    }

    private function campaign(User $owner, Segment $segment): Campaign
    {
        return Campaign::query()->create(['organization_id' => $owner->organization_id, 'created_by' => $owner->id, 'segment_id' => $segment->id, 'name' => 'News', 'channel' => 'mail', 'content' => 'Hello', 'status' => 'scheduled', 'scheduled_at' => now()]);
    }

    private function article(User $owner, int $segmentId): Article
    {
        return Article::query()->create(['organization_id' => $owner->organization_id, 'created_by' => $owner->id, 'segment_id' => $segmentId, 'title' => 'News', 'description' => 'Text', 'audience_segment' => 'all_users', 'status' => 'published']);
    }
}
