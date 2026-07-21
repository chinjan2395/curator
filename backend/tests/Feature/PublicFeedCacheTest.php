<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use App\Services\EmbedPublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFeedCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_feed_cache_is_busted_after_embed_publish(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'cache-bust-public-key-1234567890',
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'YouTube feed',
            'type' => 'youtube',
            'source_url' => 'https://youtube.com/channel/test',
        ]);

        Post::query()->create([
            'feed_id' => $feed->id,
            'title' => 'Approved draft',
            'content' => 'Body',
            'thumbnail_url' => null,
            'video_url' => 'https://youtube.com/watch?v=abc',
            'posted_at' => now(),
            'external_id' => 'yt-1',
            'status' => 'approved',
            'pinned' => false,
            'published_at' => null,
        ]);

        $url = '/api/public/feeds/'.$workspace->public_key.'/posts';

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        app(EmbedPublishService::class)->publish($workspace->fresh());

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('posts.0.external_id', 'yt-1');
    }
}
