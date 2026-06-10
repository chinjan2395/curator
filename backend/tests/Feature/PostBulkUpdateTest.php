<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_update_marks_selected_workspace_posts(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $feedA = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Feed A',
            'type' => 'rss',
            'source_url' => 'https://example.com/a.xml',
        ]);
        $feedB = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Feed B',
            'type' => 'rss',
            'source_url' => 'https://example.com/b.xml',
        ]);

        $postA = $this->createPost($feedA, 'post-a');
        $postB = $this->createPost($feedB, 'post-b');
        $unselected = $this->createPost($feedA, 'post-c');

        $this->actingAs($user)->putJson("/api/workspaces/{$workspace->id}/posts/bulk", [
            'post_ids' => [$postA->id, $postB->id],
            'status' => 'approved',
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'approved');

        $this->assertSame('approved', $postA->fresh()->status);
        $this->assertSame('approved', $postB->fresh()->status);
        $this->assertSame('pending', $unselected->fresh()->status);
    }

    public function test_bulk_update_rejects_posts_outside_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $otherWorkspace = Workspace::query()->create([
            'name' => 'Other',
            'owner_id' => $user->id,
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Feed',
            'type' => 'rss',
            'source_url' => 'https://example.com/feed.xml',
        ]);
        $otherFeed = Feed::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Other Feed',
            'type' => 'rss',
            'source_url' => 'https://example.com/other.xml',
        ]);

        $post = $this->createPost($feed, 'post-a');
        $otherPost = $this->createPost($otherFeed, 'post-b');

        $this->actingAs($user)->putJson("/api/workspaces/{$workspace->id}/posts/bulk", [
            'post_ids' => [$post->id, $otherPost->id],
            'status' => 'approved',
        ])->assertNotFound();

        $this->assertSame('pending', $post->fresh()->status);
        $this->assertSame('pending', $otherPost->fresh()->status);
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
