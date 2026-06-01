<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbedFeedAliasTest extends TestCase
{
    use RefreshDatabase;

    public function test_embed_feed_alias_matches_public_feed_route(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Test WS',
            'public_key' => 'test-public-key-123',
        ]);
        $feed = Feed::create([
            'workspace_id' => $workspace->id,
            'name' => 'YT',
            'type' => 'youtube',
            'source_url' => 'https://youtube.com/channel/test',
        ]);
        Post::create([
            'feed_id' => $feed->id,
            'title' => 'Post',
            'content' => 'Test post content',
            'status' => 'approved',
            'published_at' => now(),
            'posted_at' => now(),
            'external_id' => 'ext-1',
        ]);

        $legacy = $this->getJson('/api/public/feeds/'.$workspace->public_key.'/posts');
        $alias = $this->getJson('/api/embed/'.$workspace->public_key.'/feed');

        $legacy->assertOk();
        $alias->assertOk();
        $this->assertSame(
            $legacy->json('meta.total'),
            $alias->json('meta.total'),
        );
    }
}
