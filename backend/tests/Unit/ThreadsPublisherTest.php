<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Services\Social\Publishers\ThreadsPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ThreadsPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_text_thread(): void
    {
        Http::fake([
            'https://graph.threads.net/v1.0/threads-user-9/threads' => Http::response(['id' => 'container_text'], 200),
            'https://graph.threads.net/v1.0/threads-user-9/threads_publish' => Http::response(['id' => 'thread_post_1'], 200),
            'https://graph.threads.net/v1.0/thread_post_1*' => Http::response([
                'permalink' => 'https://www.threads.net/@creator/post/abc',
            ], 200),
        ]);

        $scheduled = $this->scheduledPost(
            caption: 'Shipping today',
            mediaUrls: null,
        );

        $result = (new ThreadsPublisher)->publish($scheduled);

        $this->assertSame('thread_post_1', $result['platform_post_id']);
        $this->assertSame('https://www.threads.net/@creator/post/abc', $result['platform_post_url']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.threads.net/v1.0/threads-user-9/threads'
                && ($request['media_type'] ?? '') === 'TEXT'
                && ($request['text'] ?? '') === 'Shipping today';
        });
    }

    public function test_publishes_image_thread_after_container_ready(): void
    {
        Http::fake([
            'https://graph.threads.net/v1.0/threads-user-9/threads' => Http::response(['id' => 'container_img'], 200),
            'https://graph.threads.net/v1.0/container_img*' => Http::response(['status' => 'FINISHED'], 200),
            'https://graph.threads.net/v1.0/threads-user-9/threads_publish' => Http::response(['id' => 'thread_post_2'], 200),
            'https://graph.threads.net/v1.0/thread_post_2*' => Http::response([
                'permalink' => 'https://www.threads.net/@creator/post/xyz',
            ], 200),
        ]);

        $scheduled = $this->scheduledPost(
            caption: 'Photo drop',
            mediaUrls: ['https://cdn.example.com/shot.jpg'],
        );

        $result = (new ThreadsPublisher)->publish($scheduled);

        $this->assertSame('thread_post_2', $result['platform_post_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.threads.net/v1.0/threads-user-9/threads'
                && ($request['media_type'] ?? '') === 'IMAGE'
                && ($request['image_url'] ?? '') === 'https://cdn.example.com/shot.jpg';
        });
    }

    public function test_publishes_carousel_thread(): void
    {
        Http::fake([
            'https://graph.threads.net/v1.0/threads-user-9/threads' => Http::sequence()
                ->push(['id' => 'child_1'], 200)
                ->push(['id' => 'child_2'], 200)
                ->push(['id' => 'carousel_1'], 200),
            'https://graph.threads.net/v1.0/child_1*' => Http::response(['status' => 'FINISHED'], 200),
            'https://graph.threads.net/v1.0/child_2*' => Http::response(['status' => 'FINISHED'], 200),
            'https://graph.threads.net/v1.0/carousel_1*' => Http::response(['status' => 'FINISHED'], 200),
            'https://graph.threads.net/v1.0/threads-user-9/threads_publish' => Http::response(['id' => 'thread_post_3'], 200),
            'https://graph.threads.net/v1.0/thread_post_3*' => Http::response([
                'permalink' => 'https://www.threads.net/@creator/post/carousel',
            ], 200),
        ]);

        $scheduled = $this->scheduledPost(
            caption: 'Carousel drop',
            mediaUrls: [
                'https://cdn.example.com/one.jpg',
                'https://cdn.example.com/two.jpg',
            ],
        );

        $result = (new ThreadsPublisher)->publish($scheduled);

        $this->assertSame('thread_post_3', $result['platform_post_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.threads.net/v1.0/threads-user-9/threads'
                && ($request['media_type'] ?? '') === 'CAROUSEL'
                && ($request['children'] ?? '') === 'child_1,child_2';
        });
    }

    private function scheduledPost(string $caption, ?array $mediaUrls): ScheduledPost
    {
        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'threads',
            'account_id' => 'threads-user-9',
            'access_token' => 'threads-token',
            'expires_at' => now()->addDay(),
            'status' => 'active',
        ]);

        $campaign = Campaign::create(['user_id' => $user->id, 'name' => 'C', 'status' => 'generated']);
        $package = ContentPackage::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => 'threads',
            'content_type' => 'post',
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
