<?php

namespace Tests\Feature;

use App\Events\FeedSyncUpdated;
use App\Events\NotificationCreated;
use App\Events\ScheduledPostStatusChanged;
use App\Models\Campaign;
use App\Models\Feed;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_generate_dispatches_job(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Realtime campaign',
            'status' => 'draft',
            'platforms' => ['instagram'],
        ]);

        $this->postJson("/api/campaigns/{$campaign->id}/generate")
            ->assertAccepted()
            ->assertJsonPath('data.queued', true);

        $this->assertDatabaseHas('content_packages', [
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_feed_sync_dispatches_job(): void
    {
        Event::fake([FeedSyncUpdated::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $credential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'rss',
            'status' => 'active',
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'social_credential_id' => $credential->id,
            'name' => 'RSS feed',
            'type' => 'rss',
            'source_url' => 'https://example.com/feed.xml',
        ]);

        $this->postJson("/api/workspaces/{$workspace->id}/feeds/{$feed->id}/sync")
            ->assertAccepted()
            ->assertJsonPath('data.queued', true);
    }

    public function test_publisher_emits_status_event_on_publish(): void
    {
        Event::fake([ScheduledPostStatusChanged::class]);

        $user = User::factory()->create();
        $credential = SocialCredential::factory()->create([
            'user_id' => $user->id,
            'provider' => 'stub',
            'status' => 'active',
        ]);
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'C',
            'status' => 'generated',
        ]);
        $package = \App\Models\ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Hello',
            'status' => 'approved',
        ]);
        $post = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now()->subMinute(),
            'status' => 'scheduled',
        ]);

        app(\App\Services\Social\SocialPublisherService::class)->publish($post);

        Event::assertDispatched(ScheduledPostStatusChanged::class);
    }

    public function test_notification_service_broadcasts_created_event(): void
    {
        Event::fake([NotificationCreated::class]);

        $user = User::factory()->create();
        app(\App\Services\NotificationService::class)->notify(
            $user,
            'post_published',
            'Post published',
            'Your post went live.',
        );

        Event::assertDispatched(NotificationCreated::class);
    }

    public function test_analytics_insights_dispatches_job(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/analytics/insights')
            ->assertAccepted()
            ->assertJsonPath('data.queued', true);
    }

    public function test_duplicate_scan_dispatches_job(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $workspace = Workspace::query()->create([
            'name' => 'Dup workspace',
            'owner_id' => $user->id,
        ]);

        $this->postJson("/api/workspaces/{$workspace->id}/duplicate-groups/scan")
            ->assertAccepted()
            ->assertJsonPath('data.queued', true);
    }

    public function test_admin_run_all_dispatches_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/sync/run-all')
            ->assertAccepted()
            ->assertJsonPath('queued', true);
    }
}
