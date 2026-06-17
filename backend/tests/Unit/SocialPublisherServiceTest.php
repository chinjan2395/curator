<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Services\Social\SocialPublisherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialPublisherServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_when_credential_was_partially_eager_loaded(): void
    {
        Http::fake([
            'api.x.com/2/tweets' => Http::response([
                'data' => ['id' => '1234567890'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'twitter',
            'account_id' => 'acct-1',
            'access_token' => 'live-access-token',
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
            'caption' => 'Scheduled via publisher service',
            'status' => 'approved',
        ]);

        $scheduled = ScheduledPost::create([
            'user_id' => $user->id,
            'social_credential_id' => $credential->id,
            'content_package_id' => $package->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        // Mimic PublishScheduledPostsCommand trimming relation columns.
        $scheduled->load(['socialCredential:id,provider', 'contentPackage:id,caption']);

        (new SocialPublisherService)->publish($scheduled);
        $scheduled->refresh();

        $this->assertSame('published', $scheduled->status);
        $this->assertSame('1234567890', $scheduled->platform_post_id);
    }
}
