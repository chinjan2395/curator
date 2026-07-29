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
use RuntimeException;
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
            'graph.facebook.com/v23.0/container_1*' => Http::response(['status_code' => 'FINISHED'], 200),
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
            'graph.facebook.com/v23.0/child_1*' => Http::response(['status_code' => 'FINISHED'], 200),
            'graph.facebook.com/v23.0/child_2*' => Http::response(['status_code' => 'FINISHED'], 200),
            'graph.facebook.com/v23.0/carousel_1*' => Http::response(['status_code' => 'FINISHED'], 200),
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

    public function test_waits_for_the_container_before_publishing_an_image(): void
    {
        Http::fake([
            'graph.facebook.com/v23.0/me/accounts*' => Http::response([
                'data' => [['id' => '111222333', 'access_token' => 'page-token']],
            ], 200),
            'graph.facebook.com/v23.0/17841400000/media' => Http::response(['id' => 'container_slow'], 200),
            'graph.facebook.com/v23.0/container_slow*' => Http::sequence()
                ->push(['status_code' => 'IN_PROGRESS'], 200)
                ->push(['status_code' => 'FINISHED'], 200),
            'graph.facebook.com/v23.0/17841400000/media_publish' => Http::response(['id' => 'media_slow'], 200),
            'graph.facebook.com/v23.0/media_slow*' => Http::response(['permalink' => 'https://www.instagram.com/p/slow/'], 200),
        ]);

        $scheduled = $this->scheduledInstagramPost(
            ['https://cdn.example.com/photo.jpg'],
            'pk-ig-slow',
        );

        $result = (new InstagramPublisher)->publish($scheduled);

        $this->assertSame('media_slow', $result['platform_post_id']);

        $urls = Http::recorded()->map(fn ($pair) => $pair[0]->url())->values()->all();
        $statusPolls = array_keys(array_filter($urls, fn ($url) => str_contains($url, '/container_slow')));
        $publishIndex = array_search(true, array_map(
            fn ($url) => str_contains($url, '/media_publish'),
            $urls,
        ), true);

        // Publishing must only happen after the container stops reporting IN_PROGRESS.
        $this->assertCount(2, $statusPolls);
        $this->assertGreaterThan(max($statusPolls), $publishIndex);
    }

    public function test_surfaces_instagram_processing_error_instead_of_publishing(): void
    {
        Http::fake([
            'graph.facebook.com/v23.0/me/accounts*' => Http::response([
                'data' => [['id' => '111222333', 'access_token' => 'page-token']],
            ], 200),
            'graph.facebook.com/v23.0/17841400000/media' => Http::response(['id' => 'container_err'], 200),
            'graph.facebook.com/v23.0/container_err*' => Http::response([
                'status_code' => 'ERROR',
                'status' => 'Error: Media format not supported',
            ], 200),
        ]);

        $scheduled = $this->scheduledInstagramPost(
            ['https://cdn.example.com/photo.jpg'],
            'pk-ig-err',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media format not supported');

        (new InstagramPublisher)->publish($scheduled);
    }

    /**
     * @param  list<string>  $mediaUrls
     */
    private function scheduledInstagramPost(array $mediaUrls, string $publicKey): ScheduledPost
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'WS', 'public_key' => $publicKey]);
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
            'media_urls' => $mediaUrls,
            'status' => 'approved',
        ]);

        return ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);
    }
}
