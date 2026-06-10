<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostWorkspaceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_index_returns_posts_across_feeds(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $feedA = $this->createFeed($workspace, 'Feed A');
        $feedB = $this->createFeed($workspace, 'Feed B');
        $postA = $this->createPost($feedA, 'post-a');
        $postB = $this->createPost($feedB, 'post-b');

        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->id}/posts")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $postA->id, 'feed_id' => $feedA->id])
            ->assertJsonFragment(['id' => $postB->id, 'feed_id' => $feedB->id]);
    }

    public function test_workspace_index_can_filter_by_feed_id(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $feedA = $this->createFeed($workspace, 'Feed A');
        $feedB = $this->createFeed($workspace, 'Feed B');
        $postA = $this->createPost($feedA, 'post-a');
        $this->createPost($feedB, 'post-b');

        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->id}/posts?feed_id={$feedA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $postA->id);
    }

    public function test_workspace_index_can_return_posts_updated_since_timestamp(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $feed = $this->createFeed($workspace, 'Feed');
        $olderPost = $this->createPost($feed, 'post-a');
        $newerPost = $this->createPost($feed, 'post-b');

        $olderPost->forceFill(['updated_at' => now()->subHour()])->save();
        $newerPost->forceFill(['updated_at' => now()])->save();

        $since = now()->subMinutes(30)->toIso8601String();

        $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->id}/posts?since=".urlencode($since))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newerPost->id);
    }

    public function test_workspace_index_rejects_non_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $owner->id,
        ]);
        $feed = $this->createFeed($workspace, 'Feed');
        $this->createPost($feed, 'post-a');

        $this->actingAs($otherUser)
            ->getJson("/api/workspaces/{$workspace->id}/posts")
            ->assertForbidden();
    }

    private function createFeed(Workspace $workspace, string $name): Feed
    {
        return Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'type' => 'rss',
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
