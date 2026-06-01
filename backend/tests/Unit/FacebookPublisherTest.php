<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\Feed;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\Publishers\FacebookPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_page_feed_post(): void
    {
        Http::fake([
            'graph.facebook.com/v23.0/me/accounts*' => Http::response([
                'data' => [['id' => '555666777', 'access_token' => 'page-token']],
            ], 200),
            'graph.facebook.com/v23.0/555666777/feed' => Http::response(['id' => 'page_post_123'], 200),
        ]);

        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'WS', 'public_key' => 'pk-fb']);
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'account_id' => 'user-fb',
            'access_token' => 'user-token',
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ]);
        Feed::create([
            'workspace_id' => $workspace->id,
            'name' => 'FB Page',
            'type' => 'facebook',
            'source_url' => 'https://facebook.com/page',
            'social_credential_id' => $credential->id,
            'facebook_page_id' => '555666777',
        ]);

        $campaign = Campaign::create(['user_id' => $user->id, 'name' => 'C', 'status' => 'generated']);
        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'facebook',
            'content_type' => 'post',
            'caption' => 'Hello Facebook',
            'status' => 'approved',
        ]);

        $scheduled = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $result = (new FacebookPublisher)->publish($scheduled);

        $this->assertSame('page_post_123', $result['platform_post_id']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/555666777/feed')
                && ($request['message'] ?? '') === 'Hello Facebook';
        });
    }
}
