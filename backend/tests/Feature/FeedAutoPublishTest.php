<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PostSyncUpsert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeedAutoPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_sync_settings_updates_auto_publish_flag(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'RSS feed',
            'type' => 'rss',
            'source_url' => 'https://example.com/rss.xml',
        ]);

        $this->actingAs($user)->patchJson("/api/workspaces/{$workspace->id}/feeds/{$feed->id}/sync-settings", [
            'auto_publish_new_posts' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.auto_publish_new_posts', true);

        $this->assertTrue($feed->fresh()->auto_publish_new_posts);
    }

    public function test_post_sync_upsert_preserves_status_on_update(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $credential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'tiktok',
            'access_token' => 'tt-token',
            'expires_at' => now()->addHour(),
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'TikTok feed',
            'type' => 'tiktok',
            'source_url' => '',
            'social_credential_id' => $credential->id,
        ]);

        $upsert = app(PostSyncUpsert::class);
        $upsert->upsert($feed, 'ext-1', [
            'title' => 'First title',
            'content' => 'Body',
            'thumbnail_url' => null,
            'video_url' => null,
            'posted_at' => now(),
        ]);

        $post = Post::query()->where('external_id', 'ext-1')->firstOrFail();
        $this->assertSame('pending', $post->status);

        $post->update(['status' => 'rejected']);

        $upsert->upsert($feed->fresh(), 'ext-1', [
            'title' => 'Updated title',
            'content' => 'Body 2',
            'thumbnail_url' => null,
            'video_url' => null,
            'posted_at' => now(),
        ]);

        $post->refresh();
        $this->assertSame('rejected', $post->status);
        $this->assertSame('Updated title', $post->title);
    }

    public function test_auto_publish_on_insert_sets_approved_and_workspace_public_key(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'RSS feed',
            'type' => 'rss',
            'source_url' => 'https://example.com/rss.xml',
            'auto_publish_new_posts' => true,
        ]);

        app(PostSyncUpsert::class)->upsert($feed, 'item-1', [
            'title' => 'Hello',
            'content' => 'World',
            'thumbnail_url' => null,
            'video_url' => 'https://example.com/p/1',
            'posted_at' => now(),
        ]);

        $post = Post::query()->where('external_id', 'item-1')->firstOrFail();
        $this->assertSame('approved', $post->status);
        $this->assertNotNull($post->published_at);

        $workspace->refresh();
        $this->assertNotNull($workspace->public_key);
    }

    public function test_tiktok_sync_auto_publish_sets_approved(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $credential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'tiktok',
            'access_token' => 'tt-token',
            'expires_at' => now()->addHour(),
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'TikTok feed',
            'type' => 'tiktok',
            'source_url' => '',
            'social_credential_id' => $credential->id,
            'auto_publish_new_posts' => true,
        ]);

        Http::fake([
            'https://open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => [
                    'user' => [
                        'open_id' => 'open-999',
                        'username' => 'creator',
                        'display_name' => 'Creator Name',
                    ],
                ],
            ], 200),
            'https://open.tiktokapis.com/v2/video/list/*' => Http::response([
                'data' => [
                    'videos' => [
                        [
                            'id' => 'video-auto-1',
                            'title' => '',
                            'video_description' => '',
                            'cover_image_url' => 'https://cdn.example/cover.jpg',
                            'share_url' => 'https://www.tiktok.com/@creator/video/1',
                            'create_time' => 1710000000,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/feeds/{$feed->id}/sync")->assertAccepted();

        $post = Post::query()->where('feed_id', $feed->id)->where('external_id', 'video-auto-1')->first();
        $this->assertNotNull($post);
        $this->assertSame('approved', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_public_feed_excludes_rejected_posts(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'testpubkey1234567890123456789012',
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'RSS feed',
            'type' => 'rss',
            'source_url' => 'https://example.com/rss.xml',
        ]);

        Post::query()->create([
            'feed_id' => $feed->id,
            'title' => 'Hidden',
            'content' => '',
            'thumbnail_url' => null,
            'video_url' => null,
            'posted_at' => now(),
            'external_id' => 'rej-1',
            'status' => 'rejected',
            'pinned' => false,
            'published_at' => now(),
        ]);

        Post::query()->create([
            'feed_id' => $feed->id,
            'title' => 'Visible',
            'content' => '',
            'thumbnail_url' => null,
            'video_url' => null,
            'posted_at' => now(),
            'external_id' => 'ok-1',
            'status' => 'approved',
            'pinned' => false,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/public/feeds/'.$workspace->public_key.'/posts');

        $response->assertOk();
        $externalIds = collect($response->json('posts'))->pluck('external_id')->all();
        $this->assertContains('ok-1', $externalIds);
        $this->assertNotContains('rej-1', $externalIds);
    }
}
