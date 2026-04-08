<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_feed_requires_valid_tiktok_credential(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $wrongCredential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'twitter',
            'access_token' => 'x-token',
        ]);

        $response = $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/feeds", [
            'name' => 'TikTok feed',
            'type' => 'tiktok',
            'social_credential_id' => $wrongCredential->id,
            'source_url' => '',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Select a valid TikTok credential.',
            ]);
    }

    public function test_test_tiktok_endpoint_returns_connected_account_data(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $credential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'tiktok',
            'access_token' => 'tt-token',
            'expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => [
                    'user' => [
                        'open_id' => 'open-123',
                        'username' => 'creator',
                        'display_name' => 'Creator Name',
                        'avatar_url' => 'https://cdn.example/avatar.jpg',
                    ],
                ],
            ], 200),
            'https://open.tiktokapis.com/v2/video/list/*' => Http::response([
                'data' => [
                    'videos' => [
                        ['id' => 'v1'],
                        ['id' => 'v2'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/feeds/test-tiktok", [
            'social_credential_id' => $credential->id,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'TikTok connection successful.',
                'open_id' => 'open-123',
                'username' => 'creator',
                'display_name' => 'Creator Name',
                'sample_count' => 2,
            ]);
    }

    public function test_sync_tiktok_creates_posts_and_normalizes_unix_timestamp(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $credential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'tiktok',
            'access_token' => 'tt-token',
            'expires_at' => now()->addHour(),
        ]);
        $feed = Feed::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'TikTok feed',
            'type' => 'tiktok',
            'source_url' => '',
            'social_credential_id' => $credential->id,
        ]);

        Http::fake([
            'https://open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => [
                    'user' => [
                        'open_id' => 'open-999',
                        'username' => 'creator',
                        'display_name' => 'Creator Name',
                    ],
                ],
            ], 200),
            'https://open.tiktokapis.com/v2/video/list/*' => Http::response([
                'data' => [
                    'videos' => [
                        [
                            'id' => 'video-1',
                            'title' => '',
                            'video_description' => '',
                            'cover_image_url' => 'https://cdn.example/cover.jpg',
                            'share_url' => 'https://www.tiktok.com/@creator/video/1',
                            'create_time' => 1710000000,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/feeds/{$feed->id}/sync");

        $response->assertOk()->assertJson([
            'message' => 'TikTok sync complete',
            'created' => 1,
        ]);

        $post = Post::query()->where('feed_id', $feed->id)->where('external_id', 'video-1')->first();
        $this->assertNotNull($post);
        $this->assertSame('TikTok video', $post->title);
        $this->assertSame(1710000000, $post->posted_at?->timestamp);
    }

    public function test_tiktok_account_endpoint_rejects_non_tiktok_credential(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $wrongCredential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'access_token' => 'fb-token',
        ]);

        $response = $this->actingAs($user)->getJson("/api/workspaces/{$workspace->id}/feeds/tiktok/account?social_credential_id={$wrongCredential->id}");

        $response
            ->assertStatus(404)
            ->assertJson([
                'message' => 'TikTok credential not found for this user.',
            ]);
    }
}

