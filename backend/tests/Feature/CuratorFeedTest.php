<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuratorFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_curator_feed_returns_posts_across_workspaces(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'pk-test',
        ]);
        $youtubeFeed = $this->createFeed($workspace, 'YT', 'youtube');
        $rssFeed = $this->createFeed($workspace, 'RSS', 'rss');
        $youtubePost = $this->createPost($youtubeFeed, 'yt-1');
        $rssPost = $this->createPost($rssFeed, 'rss-1');

        $this->actingAs($user)
            ->getJson('/api/curator/feed')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data')
            ->assertJsonFragment(['id' => $youtubePost->id])
            ->assertJsonFragment(['id' => $rssPost->id]);
    }

    public function test_curator_feed_can_filter_by_platform(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'pk-test',
        ]);
        $youtubeFeed = $this->createFeed($workspace, 'YT', 'youtube');
        $rssFeed = $this->createFeed($workspace, 'RSS', 'rss');
        $youtubePost = $this->createPost($youtubeFeed, 'yt-1');
        $this->createPost($rssFeed, 'rss-1');

        $this->actingAs($user)
            ->getJson('/api/curator/feed?platform=youtube')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $youtubePost->id);
    }

    public function test_curator_feed_without_platform_returns_all_posts(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'pk-test',
        ]);
        $youtubeFeed = $this->createFeed($workspace, 'YT', 'youtube');
        $rssFeed = $this->createFeed($workspace, 'RSS', 'rss');
        $this->createPost($youtubeFeed, 'yt-1');
        $this->createPost($rssFeed, 'rss-1');

        $this->actingAs($user)
            ->getJson('/api/curator/feed?platform=')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');
    }

    private function createFeed(Workspace $workspace, string $name, string $type): Feed
    {
        return Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'type' => $type,
            'source_url' => 'https://example.com/feed.xml',
        ]);
    }

    private function createPost(Feed $feed, string $externalId): Post
    {
        return Post::query()->create([
            'feed_id' => $feed->id,
            'content' => 'Test post',
            'posted_at' => now(),
            'external_id' => $externalId,
            'status' => 'pending',
            'pinned' => false,
        ]);
    }
}
