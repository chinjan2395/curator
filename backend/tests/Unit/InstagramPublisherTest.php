<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\Feed;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Social\Publishers\InstagramPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_image_container(): void
    {
        Http::fake([
            'graph.facebook.com/v23.0/me/accounts*' => Http::response([
                'data' => [['id' => '111222333', 'access_token' => 'page-token']],
            ], 200),
            'graph.facebook.com/v23.0/17841400000/media' => Http::response(['id' => 'container_1'], 200),
            'graph.facebook.com/v23.0/17841400000/media_publish' => Http::response(['id' => 'media_99'], 200),
            'graph.facebook.com/v23.0/media_99*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/'], 200),
        ]);

        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'WS', 'public_key' => 'pk-ig']);
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'instagram',
            'account_id' => 'ig-user',
            'access_token' => 'user-token',
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ]);
        Feed::create([
            'workspace_id' => $workspace->id,
            'name' => 'IG',
            'type' => 'instagram',
            'source_url' => 'https://instagram.com/test',
            'social_credential_id' => $credential->id,
            'facebook_page_id' => '111222333',
            'instagram_business_account_id' => '17841400000',
        ]);

        $campaign = Campaign::create(['user_id' => $user->id, 'name' => 'C', 'status' => 'generated']);
        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'IG launch',
            'media_urls' => ['https://cdn.example.com/photo.jpg'],
            'status' => 'approved',
        ]);

        $scheduled = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $result = (new InstagramPublisher)->publish($scheduled);

        $this->assertSame('media_99', $result['platform_post_id']);
        $this->assertSame('https://www.instagram.com/p/abc/', $result['platform_post_url']);
    }

    public function test_publishes_carousel_container(): void
    {
        Http::fake([
            'graph.facebook.com/v23.0/me/accounts*' => Http::response([
                'data' => [['id' => '111222333', 'access_token' => 'page-token']],
            ], 200),
            'graph.facebook.com/v23.0/17841400000/media' => Http::sequence()
                ->push(['id' => 'child_1'], 200)
                ->push(['id' => 'child_2'], 200)
                ->push(['id' => 'carousel_1'], 200),
            'graph.facebook.com/v23.0/17841400000/media_publish' => Http::response(['id' => 'media_carousel'], 200),
            'graph.facebook.com/v23.0/media_carousel*' => Http::response(['permalink' => 'https://www.instagram.com/p/carousel/'], 200),
        ]);

        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'WS', 'public_key' => 'pk-ig2']);
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'instagram',
            'account_id' => 'ig-user',
            'access_token' => 'user-token',
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ]);
        Feed::create([
            'workspace_id' => $workspace->id,
            'name' => 'IG',
            'type' => 'instagram',
            'source_url' => 'https://instagram.com/test',
            'social_credential_id' => $credential->id,
            'facebook_page_id' => '111222333',
            'instagram_business_account_id' => '17841400000',
        ]);

        $campaign = Campaign::create(['user_id' => $user->id, 'name' => 'C', 'status' => 'generated']);
        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'instagram',
            'content_type' => 'post',
            'caption' => 'Carousel launch',
            'media_urls' => [
                'https://cdn.example.com/one.jpg',
                'https://cdn.example.com/two.jpg',
            ],
            'status' => 'approved',
        ]);

        $scheduled = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $result = (new InstagramPublisher)->publish($scheduled);

        $this->assertSame('media_carousel', $result['platform_post_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v23.0/17841400000/media'
                && ($request['media_type'] ?? '') === 'CAROUSEL'
                && ($request['children'] ?? '') === 'child_1,child_2';
        });
    }
}

