<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Services\Social\Publishers\LinkedInPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LinkedInPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_text_post(): void
    {
        Http::fake([
            'https://api.linkedin.com/rest/posts' => Http::response('', 201, [
                'x-restli-id' => 'urn:li:share:7123456789012345678',
            ]),
        ]);

        $scheduled = $this->scheduledPost('Launch day update', null);
        $result = (new LinkedInPublisher)->publish($scheduled);

        $this->assertSame('urn:li:share:7123456789012345678', $result['platform_post_id']);
        $this->assertStringContainsString('7123456789012345678', (string) $result['platform_post_url']);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.linkedin.com/rest/posts') {
                return false;
            }

            $body = $request->data();

            return ($body['author'] ?? '') === 'urn:li:person:member-42'
                && ($body['commentary'] ?? '') === 'Launch day update'
                && ($body['lifecycleState'] ?? '') === 'PUBLISHED'
                && ! isset($body['content']);
        });
    }

    public function test_publishes_article_post_when_media_url_is_link(): void
    {
        Http::fake([
            'https://api.linkedin.com/rest/posts' => Http::response('', 201, [
                'x-restli-id' => 'urn:li:share:999',
            ]),
        ]);

        $scheduled = $this->scheduledPost(
            'Read our latest guide',
            ['https://example.com/blog/new-feature'],
            'article',
        );

        $result = (new LinkedInPublisher)->publish($scheduled);

        $this->assertSame('urn:li:share:999', $result['platform_post_id']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['content']['article']['source'] ?? '') === 'https://example.com/blog/new-feature';
        });
    }

    private function scheduledPost(string $caption, ?array $mediaUrls, string $contentType = 'post'): ScheduledPost
    {
        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'linkedin',
            'account_id' => 'member-42',
            'access_token' => 'linkedin-token',
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ]);

        $campaign = Campaign::create(['user_id' => $user->id, 'name' => 'C', 'status' => 'generated']);
        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'linkedin',
            'content_type' => $contentType,
            'caption' => $caption,
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
