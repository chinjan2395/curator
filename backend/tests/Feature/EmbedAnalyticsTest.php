<?php

namespace Tests\Feature;

use App\Models\EmbedPostEvent;
use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmbedAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function seedPublishedPost(?Workspace $workspace = null): array
    {
        $user = User::factory()->create();
        $workspace ??= Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Analytics WS',
            'public_key' => 'embed-analytics-key',
        ]);
        $feed = Feed::create([
            'workspace_id' => $workspace->id,
            'name' => 'YT',
            'type' => 'youtube',
            'source_url' => 'https://youtube.com/channel/test',
        ]);
        $post = Post::create([
            'feed_id' => $feed->id,
            'title' => 'Tracked Post',
            'content' => 'Test post content',
            'status' => 'approved',
            'published_at' => now(),
            'posted_at' => now(),
            'external_id' => 'ext-tracked',
            'post_url' => 'https://www.youtube.com/watch?v=abc123',
            'video_url' => 'https://www.youtube.com/watch?v=abc123',
        ]);

        return compact('user', 'workspace', 'feed', 'post');
    }

    public function test_records_click_for_published_post_in_workspace(): void
    {
        $seed = $this->seedPublishedPost();

        $response = $this->postJson(
            '/api/public/feeds/'.$seed['workspace']->public_key.'/posts/'.$seed['post']->id.'/events',
            [
                'event_type' => EmbedPostEvent::TYPE_POST_CLICK,
                'target_url' => 'https://www.youtube.com/watch?v=abc123',
                'page_url' => 'https://customer-site.test/blog',
                'referrer' => 'https://google.com',
            ],
        );

        $response->assertNoContent();
        $this->assertDatabaseHas('embed_post_events', [
            'workspace_id' => $seed['workspace']->id,
            'post_id' => $seed['post']->id,
            'event_type' => EmbedPostEvent::TYPE_POST_CLICK,
            'target_url' => 'https://www.youtube.com/watch?v=abc123',
        ]);
    }

    public function test_rejects_click_for_post_outside_workspace(): void
    {
        $seed = $this->seedPublishedPost();
        $other = $this->seedPublishedPost();

        $this->postJson(
            '/api/public/feeds/'.$seed['workspace']->public_key.'/posts/'.$other['post']->id.'/events',
            ['event_type' => EmbedPostEvent::TYPE_POST_CLICK],
        )->assertNotFound();

        $this->assertDatabaseCount('embed_post_events', 0);
    }

    public function test_rejects_click_for_unpublished_post(): void
    {
        $seed = $this->seedPublishedPost();
        $seed['post']->update(['published_at' => null]);

        $this->postJson(
            '/api/public/feeds/'.$seed['workspace']->public_key.'/posts/'.$seed['post']->id.'/events',
            ['event_type' => EmbedPostEvent::TYPE_POST_CLICK],
        )->assertNotFound();
    }

    public function test_rejects_click_for_unapproved_post(): void
    {
        $seed = $this->seedPublishedPost();
        $seed['post']->update(['status' => 'pending']);

        $this->postJson(
            '/api/public/feeds/'.$seed['workspace']->public_key.'/posts/'.$seed['post']->id.'/events',
            ['event_type' => EmbedPostEvent::TYPE_POST_CLICK],
        )->assertNotFound();
    }

    public function test_analytics_overview_includes_embed_click_totals(): void
    {
        $seed = $this->seedPublishedPost();

        EmbedPostEvent::query()->create([
            'workspace_id' => $seed['workspace']->id,
            'post_id' => $seed['post']->id,
            'event_type' => EmbedPostEvent::TYPE_POST_CLICK,
            'target_url' => 'https://www.youtube.com/watch?v=abc123',
        ]);
        EmbedPostEvent::query()->create([
            'workspace_id' => $seed['workspace']->id,
            'post_id' => $seed['post']->id,
            'event_type' => EmbedPostEvent::TYPE_SHARE_CLICK,
            'target_url' => 'https://www.youtube.com/watch?v=abc123',
        ]);

        Sanctum::actingAs($seed['user']);

        $response = $this->getJson('/api/analytics/overview');

        $response->assertOk()
            ->assertJsonPath('data.total_embed_clicks', 1)
            ->assertJsonPath('data.top_embed_clicked_posts.0.id', $seed['post']->id)
            ->assertJsonPath('data.top_embed_clicked_posts.0.clicks', 1)
            ->assertJsonPath('data.top_embed_clicked_posts.0.platform', 'youtube')
            ->assertJsonPath('data.embed_clicks_by_platform.0.platform', 'youtube')
            ->assertJsonPath('data.embed_clicks_by_platform.0.clicks', 1);
    }

    public function test_platform_analytics_includes_embed_clicks(): void
    {
        $seed = $this->seedPublishedPost();

        EmbedPostEvent::query()->create([
            'workspace_id' => $seed['workspace']->id,
            'post_id' => $seed['post']->id,
            'event_type' => EmbedPostEvent::TYPE_POST_CLICK,
        ]);

        Sanctum::actingAs($seed['user']);

        $this->getJson('/api/analytics/platforms/youtube')
            ->assertOk()
            ->assertJsonPath('data.embed_clicks', 1);
    }

    public function test_embed_js_bootstrap_includes_analytics_base(): void
    {
        $seed = $this->seedPublishedPost();

        $response = $this->get('/api/embed/'.$seed['workspace']->public_key.'.js');

        $response->assertOk();
        $this->assertStringContainsString('CRT_ANALYTICS_BASE', $response->getContent());
        $this->assertStringContainsString('/api/public/feeds/'.$seed['workspace']->public_key.'/posts', $response->getContent());
    }
}
