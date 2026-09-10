<?php

namespace Tests\Feature;

use App\Articles\Jobs\TransitionArticlePublicationStatus;
use App\Articles\Models\Article;
use App\Service\Models\Service;
use App\Users\Models\Group;
use App\Users\Models\Location;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ArticleSegmentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishable_announcements_are_segmented_and_strictly_isolated_by_organization(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Auth::setUser($user);

        $group = Group::query()->create(['name' => 'members', 'label' => 'Members']);
        $location = Location::query()->create(['name' => 'Bucharest']);
        $user->groups()->attach($group);
        $user->locations()->attach($location);

        $service = Service::query()->create([
            'name' => 'Current', 'price' => 10, 'currency' => 'EUR', 'is_active' => true,
        ]);
        $user->services()->attach($service, [
            'start_date' => today()->subMonth(), 'expires_at' => today()->addMonth(),
        ]);

        foreach (['all_users', 'active_subscribers', 'groups', 'locations'] as $segment) {
            $article = $this->article($user, $segment);
            if ($segment === 'groups') {
                $article->groups()->attach($group);
            }
            if ($segment === 'locations') {
                $article->locations()->attach($location);
            }
        }

        $this->article($user, 'expired_users');
        $this->article($otherUser, 'all_users');
        $this->article($user, 'all_users', ['status' => 'draft']);
        $this->article($user, 'all_users', ['expires_at' => now()->subMinute()]);

        $visible = Article::query()->visibleTo($user)->pluck('audience_segment');

        $this->assertEqualsCanonicalizing(['all_users', 'active_subscribers', 'groups', 'locations'], $visible->all());
        $this->assertSame(4, $visible->count());
    }

    public function test_expired_segment_excludes_users_who_also_have_an_active_service(): void
    {
        $user = User::factory()->create();
        Auth::setUser($user);
        $expired = Service::query()->create(['name' => 'Old', 'price' => 10, 'currency' => 'EUR', 'is_active' => true]);
        $user->services()->attach($expired, ['expires_at' => today()->subDay()]);
        $article = $this->article($user, 'expired_users');

        $this->assertTrue(Article::query()->visibleTo($user)->whereKey($article)->exists());

        $active = Service::query()->create(['name' => 'New', 'price' => 10, 'currency' => 'EUR', 'is_active' => true]);
        $user->services()->attach($active, ['expires_at' => today()->addDay()]);

        $this->assertFalse(Article::query()->visibleTo($user)->whereKey($article)->exists());
    }

    public function test_feed_records_delivery_and_view_endpoint_records_view(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        Auth::setUser($user);
        $article = $this->article($user, 'all_users');
        Auth::logout();
        $token = $this->postJson('/api/login', [
            'email' => $user->email, 'organization_id' => $user->organization_id, 'password' => 'password',
        ])->json('token');

        $this->withToken($token)->getJson('/api/articles-feed')
            ->assertOk()->assertJsonPath('data.0.id', $article->id);
        $this->assertDatabaseHas('article_user_receipts', [
            'article_id' => $article->id, 'user_id' => $user->id, 'viewed_at' => null,
        ]);

        $this->withToken($token)->postJson("/api/articles/{$article->id}/view")
            ->assertOk()->assertJsonPath('data.id', $article->id);
        $this->assertDatabaseMissing('article_user_receipts', [
            'article_id' => $article->id, 'user_id' => $user->id, 'viewed_at' => null,
        ]);
    }

    public function test_scheduled_process_publishes_and_expires_articles(): void
    {
        $user = User::factory()->create();
        Auth::setUser($user);
        $scheduled = $this->article($user, 'all_users', ['status' => 'scheduled', 'publish_at' => now()->subMinute()]);
        $expired = $this->article($user, 'all_users', ['expires_at' => now()->subMinute()]);

        (new TransitionArticlePublicationStatus)->handle();

        $this->assertSame('published', $scheduled->fresh()->status);
        $this->assertSame('expired', $expired->fresh()->status);
    }

    private function article(User $author, string $segment, array $overrides = []): Article
    {
        return Article::query()->create(array_merge([
            'organization_id' => $author->organization_id,
            'created_by' => $author->id,
            'title' => fake()->unique()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'published',
            'publish_at' => now()->subHour(),
            'expires_at' => now()->addDay(),
            'audience_segment' => $segment,
        ], $overrides));
    }
}
