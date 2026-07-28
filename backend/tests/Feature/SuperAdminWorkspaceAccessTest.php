<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminWorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_view_a_workspace_they_do_not_own(): void
    {
        $owner = User::factory()->create();
        $superadmin = User::factory()->superadmin()->create();
        $workspace = Workspace::query()->create(['name' => 'Owner workspace', 'owner_id' => $owner->id]);

        $this->actingAs($superadmin)
            ->getJson("/api/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $workspace->id)
            ->assertJsonPath('data.is_owner', false);
    }

    public function test_superadmin_can_list_posts_in_another_users_workspace(): void
    {
        $owner = User::factory()->create();
        $superadmin = User::factory()->superadmin()->create();
        $workspace = Workspace::query()->create(['name' => 'Owner workspace', 'owner_id' => $owner->id]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Feed',
            'type' => 'rss',
            'source_url' => 'https://example.com/feed.xml',
        ]);
        Post::query()->create([
            'feed_id' => $feed->id,
            'content' => 'Test post',
            'posted_at' => now(),
            'external_id' => 'post-a',
            'status' => 'pending',
            'pinned' => false,
        ]);

        $this->actingAs($superadmin)
            ->getJson("/api/workspaces/{$workspace->id}/posts")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_regular_admin_cannot_access_another_users_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $workspace = Workspace::query()->create(['name' => 'Owner workspace', 'owner_id' => $owner->id]);

        $this->actingAs($admin)
            ->getJson("/api/workspaces/{$workspace->id}")
            ->assertForbidden();
    }

    public function test_workspaces_index_returns_all_workspaces_for_superadmin(): void
    {
        $owner = User::factory()->create();
        $superadmin = User::factory()->superadmin()->create();
        Workspace::query()->create(['name' => 'Owner workspace', 'owner_id' => $owner->id]);
        Workspace::query()->create(['name' => 'Superadmin workspace', 'owner_id' => $superadmin->id]);

        $this->actingAs($superadmin)
            ->getJson('/api/workspaces')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_workspaces_index_only_returns_own_workspaces_for_regular_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Workspace::query()->create(['name' => 'Owner workspace', 'owner_id' => $owner->id]);
        Workspace::query()->create(['name' => 'Other workspace', 'owner_id' => $other->id]);

        $this->actingAs($owner)
            ->getJson('/api/workspaces')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_superadmin_fetches_workspace_owners_credentials_for_feed_setup(): void
    {
        $owner = User::factory()->create();
        $superadmin = User::factory()->superadmin()->create();
        $workspace = Workspace::query()->create(['name' => 'Owner workspace', 'owner_id' => $owner->id]);
        SocialCredential::query()->create([
            'user_id' => $owner->id,
            'provider' => 'youtube',
            'account_id' => 'yt-123',
            'access_token' => 'token',
            'status' => 'active',
        ]);

        $this->actingAs($superadmin)
            ->getJson("/api/workspaces/{$workspace->id}/credentials")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
