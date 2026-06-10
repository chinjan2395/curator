<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Services\Social\Publishers\TwitterPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwitterPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_tweet_via_x_api(): void
    {
        Http::fake([
            'api.x.com/2/tweets' => Http::response([
                'data' => ['id' => '1999888777'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'twitter',
            'account_id' => 'user-1',
            'access_token' => 'test-token',
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
            'platform' => 'twitter',
            'content_type' => 'post',
            'caption' => 'Hello from Curator',
            'hashtags' => ['launch'],
            'status' => 'approved',
        ]);

        $scheduled = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $result = (new TwitterPublisher)->publish($scheduled);

        $this->assertSame('1999888777', $result['platform_post_id']);
        $this->assertStringContainsString('1999888777', $result['platform_post_url']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.x.com/2/tweets'
                && $request['text'] === 'Hello from Curator #launch';
        });
    }

    public function test_rejects_video_attachment_with_clear_message(): void
    {
        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'twitter',
            'account_id' => 'user-1',
            'access_token' => 'test-token',
            'expires_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $campaign = Campaign::create(['user_id' => $user->id, 'name' => 'Launch', 'status' => 'generated']);
        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'twitter',
            'content_type' => 'video',
            'caption' => 'Clip',
            'media_urls' => ['https://cdn.example.com/clip.mp4'],
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
        $this->expectExceptionMessage('video upload is not yet supported');

        (new TwitterPublisher)->publish($scheduled);
    }
}

