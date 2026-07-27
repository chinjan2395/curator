<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaProxyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_feed_rewrites_instagram_thumbnail_to_proxy_url(): void
    {
        [$workspace, $post] = $this->seedApprovedInstagramPost(
            'https://scontent-iad3-1.cdninstagram.com/v/t51/photo.jpg?oe='.dechex(time() + 3600).'&oh=abc'
        );

        $response = $this->getJson('/api/public/feeds/'.$workspace->public_key.'/posts');

        $response->assertOk();
        $thumb = $response->json('posts.0.thumbnail_url');
        $this->assertIsString($thumb);
        $this->assertStringContainsString('/api/media/posts/'.$post->id.'/thumbnail', $thumb);
        $this->assertStringNotContainsString('cdninstagram.com', $thumb);
    }

    #[Test]
    public function proxy_serves_cached_thumbnail_bytes(): void
    {
        Storage::fake('local');

        [, $post] = $this->seedApprovedInstagramPost(
            'https://scontent.cdninstagram.com/v/t51/photo.jpg?oe='.dechex(time() + 3600)
        );

        $path = 'media-cache/posts/'.$post->id.'/thumbnail';
        Storage::disk('local')->put($path, 'fake-image-bytes');
        $post->update([
            'cached_thumbnail_path' => $path,
            'cached_thumbnail_disk' => 'local',
            'cached_thumbnail_mime' => 'image/jpeg',
        ]);

        $response = $this->get('/api/media/posts/'.$post->id.'/thumbnail');
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('fake-image-bytes', $response->streamedContent());
    }

    #[Test]
    public function proxy_downloads_and_caches_when_source_url_is_fresh(): void
    {
        Storage::fake('local');

        $source = 'https://scontent.cdninstagram.com/v/t51/fresh.jpg?oe='.dechex(time() + 7200);
        [, $post] = $this->seedApprovedInstagramPost($source);

        Http::fake([
            $source => Http::response('downloaded-image', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $response = $this->get('/api/media/posts/'.$post->id.'/thumbnail');
        $response->assertOk();
        $this->assertSame('downloaded-image', $response->streamedContent());

        $post->refresh();
        $this->assertNotNull($post->cached_thumbnail_path);
        $this->assertTrue(Storage::disk('local')->exists($post->cached_thumbnail_path));
    }

    #[Test]
    public function proxy_refreshes_expired_instagram_url_via_graph_then_caches(): void
    {
        Storage::fake('local');

        $expired = 'https://scontent.cdninstagram.com/v/t51/old.jpg?oe='.dechex(time() - 7200);
        $fresh = 'https://scontent.cdninstagram.com/v/t51/new.jpg?oe='.dechex(time() + 7200);

        [, $post, $feed] = $this->seedApprovedInstagramPost($expired, withCredential: true);
        $externalId = $post->external_id;
        $pageId = $feed->facebook_page_id;

        Http::fake([
            'https://graph.facebook.com/*' => function ($request) use ($fresh, $externalId, $pageId) {
                $url = $request->url();
                if (str_contains($url, '/me/accounts')) {
                    return Http::response([
                        'data' => [[
                            'id' => $pageId,
                            'access_token' => 'page-token',
                        ]],
                    ]);
                }

                return Http::response([
                    'id' => $externalId,
                    'media_type' => 'IMAGE',
                    'media_url' => $fresh,
                    'thumbnail_url' => $fresh,
                ]);
            },
            $fresh => Http::response('refreshed-image', 200, ['Content-Type' => 'image/jpeg']),
            $expired => Http::response('gone', 403),
        ]);

        $response = $this->get('/api/media/posts/'.$post->id.'/thumbnail');
        $response->assertOk();
        $this->assertSame('refreshed-image', $response->streamedContent());

        $post->refresh();
        $this->assertSame($fresh, $post->thumbnail_url);
        $this->assertNotNull($post->cached_thumbnail_path);
    }

    #[Test]
    public function proxy_refreshes_expired_facebook_url_via_graph_then_caches(): void
    {
        Storage::fake('local');

        $expired = 'https://scontent.xx.fbcdn.net/v/t39/old.jpg?oe='.dechex(time() - 7200);
        $fresh = 'https://scontent.xx.fbcdn.net/v/t39/new.jpg?oe='.dechex(time() + 7200);

        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'fb-public-key-'.bin2hex(random_bytes(8)),
        ]);
        $credentialId = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'account_id' => 'fb-user-1',
            'access_token' => 'user-token',
            'status' => 'active',
        ])->id;
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'FB',
            'type' => 'facebook',
            'source_url' => 'https://facebook.com/x',
            'social_credential_id' => $credentialId,
            'facebook_page_id' => 'page-456',
        ]);
        $post = Post::query()->create([
            'feed_id' => $feed->id,
            'title' => 'FB post',
            'content' => 'Caption',
            'thumbnail_url' => $expired,
            'video_url' => 'https://www.facebook.com/x/posts/1',
            'posted_at' => now(),
            'external_id' => '1234567890_1',
            'status' => 'approved',
            'pinned' => false,
            'published_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => function ($request) use ($fresh) {
                $url = $request->url();
                if (str_contains($url, '/me/accounts')) {
                    return Http::response([
                        'data' => [[
                            'id' => 'page-456',
                            'access_token' => 'page-token',
                        ]],
                    ]);
                }

                return Http::response(['full_picture' => $fresh]);
            },
            $fresh => Http::response('refreshed-image', 200, ['Content-Type' => 'image/jpeg']),
            $expired => Http::response('gone', 403),
        ]);

        $response = $this->get('/api/media/posts/'.$post->id.'/thumbnail');
        $response->assertOk();
        $this->assertSame('refreshed-image', $response->streamedContent());

        $post->refresh();
        $this->assertSame($fresh, $post->thumbnail_url);
        $this->assertNotNull($post->cached_thumbnail_path);
    }

    #[Test]
    public function youtube_thumbnails_are_not_rewritten(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'yt-public-key-1234567890123456',
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'YT',
            'type' => 'youtube',
            'source_url' => 'https://youtube.com/channel/x',
        ]);
        $ytThumb = 'https://i.ytimg.com/vi/abc123/hqdefault.jpg';
        Post::query()->create([
            'feed_id' => $feed->id,
            'title' => 'Video',
            'content' => 'Body',
            'thumbnail_url' => $ytThumb,
            'video_url' => 'https://youtube.com/watch?v=abc123',
            'posted_at' => now(),
            'external_id' => 'yt-1',
            'status' => 'approved',
            'pinned' => false,
            'published_at' => now(),
        ]);

        $this->getJson('/api/public/feeds/'.$workspace->public_key.'/posts')
            ->assertOk()
            ->assertJsonPath('posts.0.thumbnail_url', $ytThumb);
    }

    /**
     * @return array{0: Workspace, 1: Post, 2?: Feed}
     */
    private function seedApprovedInstagramPost(string $thumbnailUrl, bool $withCredential = false): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
            'public_key' => 'ig-public-key-'.bin2hex(random_bytes(8)),
        ]);

        $credentialId = null;
        if ($withCredential) {
            $credentialId = SocialCredential::query()->create([
                'user_id' => $user->id,
                'provider' => 'instagram',
                'account_id' => 'ig-user-1',
                'access_token' => 'user-token',
                'status' => 'active',
            ])->id;
        }

        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'IG',
            'type' => 'instagram',
            'source_url' => 'https://instagram.com/x',
            'social_credential_id' => $credentialId,
            'facebook_page_id' => 'page-123',
            'instagram_business_account_id' => 'ig-biz-1',
        ]);

        $post = Post::query()->create([
            'feed_id' => $feed->id,
            'title' => 'IG post',
            'content' => 'Caption',
            'thumbnail_url' => $thumbnailUrl,
            'video_url' => 'https://www.instagram.com/p/abc/',
            'posted_at' => now(),
            'external_id' => '17841400000000000',
            'status' => 'approved',
            'pinned' => false,
            'published_at' => now(),
        ]);

        return [$workspace, $post, $feed];
    }
}
