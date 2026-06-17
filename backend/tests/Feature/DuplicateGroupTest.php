<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\PostDuplicateGroup;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DuplicateGroupTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspace(User $user): Workspace
    {
        return Workspace::query()->create([
            'name'       => 'Test Workspace',
            'owner_id'   => $user->id,
            'public_key' => 'pk-' . uniqid(),
        ]);
    }

    private function makeFeed(Workspace $workspace, string $type = 'rss'): Feed
    {
        return Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name'         => 'Feed ' . uniqid(),
            'type'         => $type,
            'source_url'   => 'https://example.com/feed.xml',
        ]);
    }

    private function makePost(Feed $feed, array $attrs = []): Post
    {
        return Post::query()->create(array_merge([
            'feed_id'     => $feed->id,
            'content'     => 'Default post content for testing purposes here.',
            'posted_at'   => now(),
            'external_id' => uniqid(),
            'status'      => 'pending',
            'pinned'      => false,
        ], $attrs));
    }

    public function test_url_match_creates_group(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = $this->makeWorkspace($user);
        $feed1 = $this->makeFeed($workspace, 'youtube');
        $feed2 = $this->makeFeed($workspace, 'rss');

        $this->makePost($feed1, ['post_url' => 'https://example.com/video/123']);
        $this->makePost($feed2, ['post_url' => 'https://example.com/video/123']);

        $response = $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Scan complete.')
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('post_duplicate_groups', [
            'workspace_id' => $workspace->id,
            'status'       => 'open',
            'match_type'   => 'url',
        ]);
    }

    public function test_video_url_match_creates_group(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = $this->makeWorkspace($user);
        $feed1 = $this->makeFeed($workspace, 'youtube');
        $feed2 = $this->makeFeed($workspace, 'tiktok');

        $this->makePost($feed1, ['video_url' => 'https://cdn.example.com/clip.mp4']);
        $this->makePost($feed2, ['video_url' => 'https://cdn.example.com/clip.mp4']);

        $response = $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan");

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('post_duplicate_groups', [
            'workspace_id' => $workspace->id,
            'status'       => 'open',
            'match_type'   => 'video_url',
        ]);
    }

    public function test_text_similarity_creates_group(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = $this->makeWorkspace($user);
        $feed1 = $this->makeFeed($workspace, 'rss');
        $feed2 = $this->makeFeed($workspace, 'rss');

        $base = 'This is a very detailed caption about our new product launch event happening this weekend.';
        $this->makePost($feed1, ['content' => $base]);
        $this->makePost($feed2, ['content' => $base . ' Join us!']);

        $response = $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan");

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('post_duplicate_groups', [
            'workspace_id' => $workspace->id,
            'status'       => 'open',
            'match_type'   => 'text',
        ]);
    }

    public function test_dismissed_group_not_recreated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = $this->makeWorkspace($user);
        $feed1 = $this->makeFeed($workspace, 'rss');
        $feed2 = $this->makeFeed($workspace, 'rss');

        $this->makePost($feed1, ['post_url' => 'https://example.com/article/42']);
        $this->makePost($feed2, ['post_url' => 'https://example.com/article/42']);

        // First scan — creates open group
        $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan")->assertOk();

        $group = PostDuplicateGroup::where('workspace_id', $workspace->id)->first();
        $this->assertNotNull($group);

        // Dismiss the group
        $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/{$group->id}/dismiss")
            ->assertOk();

        // Second scan — dismissed group must not recreate as open
        $response = $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan");

        $response->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertEquals(0, PostDuplicateGroup::where('workspace_id', $workspace->id)->where('status', 'open')->count());
    }

    public function test_keep_rejects_others(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = $this->makeWorkspace($user);
        $feed1 = $this->makeFeed($workspace, 'rss');
        $feed2 = $this->makeFeed($workspace, 'rss');

        $post1 = $this->makePost($feed1, ['post_url' => 'https://example.com/keep-test']);
        $post2 = $this->makePost($feed2, ['post_url' => 'https://example.com/keep-test']);

        $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan")->assertOk();

        $group = PostDuplicateGroup::where('workspace_id', $workspace->id)->where('status', 'open')->first();
        $this->assertNotNull($group);

        $response = $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/{$group->id}/keep/{$post1->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Group resolved.');

        $this->assertDatabaseHas('post_duplicate_groups', ['id' => $group->id, 'status' => 'resolved']);
        $this->assertDatabaseHas('posts', ['id' => $post2->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('posts', ['id' => $post1->id, 'status' => 'pending']);
    }

    public function test_dismiss_does_not_change_post_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = $this->makeWorkspace($user);
        $feed1 = $this->makeFeed($workspace, 'rss');
        $feed2 = $this->makeFeed($workspace, 'rss');

        $post1 = $this->makePost($feed1, ['post_url' => 'https://example.com/dismiss-test']);
        $post2 = $this->makePost($feed2, ['post_url' => 'https://example.com/dismiss-test']);

        $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan")->assertOk();

        $group = PostDuplicateGroup::where('workspace_id', $workspace->id)->where('status', 'open')->first();

        $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/{$group->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('message', 'Group dismissed.');

        $this->assertDatabaseHas('posts', ['id' => $post1->id, 'status' => 'pending']);
        $this->assertDatabaseHas('posts', ['id' => $post2->id, 'status' => 'pending']);
        $this->assertDatabaseHas('post_duplicate_groups', ['id' => $group->id, 'status' => 'dismissed']);
    }

    public function test_index_returns_only_open_groups(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = $this->makeWorkspace($user);

        PostDuplicateGroup::create(['workspace_id' => $workspace->id, 'status' => 'open', 'match_type' => 'url']);
        PostDuplicateGroup::create(['workspace_id' => $workspace->id, 'status' => 'resolved', 'match_type' => 'url']);
        PostDuplicateGroup::create(['workspace_id' => $workspace->id, 'status' => 'dismissed', 'match_type' => 'text']);

        $response = $this->getJson("/api/workspaces/{$workspace->id}/duplicate-groups");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_scan_endpoint_returns_open_groups(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workspace = $this->makeWorkspace($user);
        $feed1 = $this->makeFeed($workspace, 'youtube');
        $feed2 = $this->makeFeed($workspace, 'rss');

        $this->makePost($feed1, ['post_url' => 'https://example.com/scan-test']);
        $this->makePost($feed2, ['post_url' => 'https://example.com/scan-test']);

        $response = $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Scan complete.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.match_type', 'url');
    }
}
