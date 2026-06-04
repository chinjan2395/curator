<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Services\Social\Publishers\TikTokPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_video_via_content_posting_api(): void
    {
        Http::fake([
            'https://open.tiktokapis.com/v2/post/publish/creator_info/query/' => Http::response([
                'data' => [
                    'creator_username' => 'creator',
                    'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'],
                ],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
            'https://open.tiktokapis.com/v2/post/publish/video/init/' => Http::response([
                'data' => ['publish_id' => 'v_pub_url~v2.test123'],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
            'https://open.tiktokapis.com/v2/post/publish/status/fetch/' => Http::response([
                'data' => [
                    'status' => 'PUBLISH_COMPLETE',
                    'publicaly_available_post_id' => ['7123456789012345678'],
                ],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
        ]);

        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'tiktok',
            'account_id' => 'open-123',
            'access_token' => 'tiktok-token',
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Launch',
            'status' => 'generated',
        ]);

        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'tiktok',
            'content_type' => 'video',
            'caption' => 'New product drop',
            'hashtags' => ['fyp'],
            'media_urls' => ['https://cdn.example.com/videos/promo.mp4'],
            'status' => 'approved',
        ]);

        $scheduled = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $result = (new TikTokPublisher)->publish($scheduled);

        $this->assertSame('7123456789012345678', $result['platform_post_id']);
        $this->assertSame(
            'https://www.tiktok.com/@creator/video/7123456789012345678',
            $result['platform_post_url'],
        );

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/post/publish/video/init/')) {
                return false;
            }

            return ($request['post_info']['title'] ?? '') === 'New product drop #fyp'
                && ($request['post_info']['privacy_level'] ?? '') === 'PUBLIC_TO_EVERYONE'
                && ($request['source_info']['source'] ?? '') === 'PULL_FROM_URL'
                && ($request['source_info']['video_url'] ?? '') === 'https://cdn.example.com/videos/promo.mp4';
        });
    }

    public function test_requires_https_video_url(): void
    {
        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'tiktok',
            'account_id' => 'open-123',
            'access_token' => 'tiktok-token',
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $campaign = Campaign::create(['user_id' => $user->id, 'name' => 'C', 'status' => 'generated']);
        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'tiktok',
            'content_type' => 'video',
            'caption' => 'Test',
            'media_urls' => ['http://insecure.example.com/v.mp4'],
            'status' => 'approved',
        ]);

        $scheduled = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('public HTTPS video URL');

        (new TikTokPublisher)->publish($scheduled);
    }
}
