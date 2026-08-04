<?php

namespace Tests\Feature;

use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeedDiscoveryAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_channels_include_avatar_url(): void
    {
        [$user, $workspace, $credential] = $this->workspaceWithCredential('youtube');

        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [
                    [
                        'id' => 'UCabc123',
                        'snippet' => [
                            'title' => 'Brand Channel',
                            'customUrl' => '@brand',
                            'thumbnails' => [
                                'default' => ['url' => 'https://yt.example/default.jpg'],
                                'medium' => ['url' => 'https://yt.example/medium.jpg'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/workspaces/{$workspace->id}/feeds/youtube/channels?social_credential_id={$credential->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('channels.0.id', 'UCabc123')
            ->assertJsonPath('channels.0.title', 'Brand Channel')
            ->assertJsonPath('channels.0.avatar_url', 'https://yt.example/medium.jpg');
    }

    public function test_facebook_pages_include_avatar_url(): void
    {
        [$user, $workspace, $credential] = $this->workspaceWithCredential('facebook');

        Http::fake([
            'https://graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => '111222',
                        'name' => 'My Page',
                        'picture' => [
                            'data' => [
                                'url' => 'https://fb.example/page.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://graph.facebook.com/*/debug_token*' => Http::response([
                'data' => [
                    'granular_scopes' => [],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/workspaces/{$workspace->id}/feeds/facebook/pages?social_credential_id={$credential->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('pages.0.id', '111222')
            ->assertJsonPath('pages.0.name', 'My Page')
            ->assertJsonPath('pages.0.avatar_url', 'https://fb.example/page.jpg');
    }

    public function test_twitter_account_includes_avatar_url(): void
    {
        [$user, $workspace, $credential] = $this->workspaceWithCredential('twitter');

        Http::fake([
            'https://api.x.com/2/users/me*' => Http::response([
                'data' => [
                    'id' => '999888',
                    'username' => 'curator',
                    'name' => 'Curator AI',
                    'profile_image_url' => 'https://x.example/avatar.jpg',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/workspaces/{$workspace->id}/feeds/twitter/account?social_credential_id={$credential->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('accounts.0.id', '999888')
            ->assertJsonPath('accounts.0.username', 'curator')
            ->assertJsonPath('accounts.0.avatar_url', 'https://x.example/avatar.jpg');
    }

    public function test_instagram_accounts_include_avatar_url(): void
    {
        [$user, $workspace, $credential] = $this->workspaceWithCredential('instagram');

        Http::fake([
            'https://graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'page-1',
                        'name' => 'Shop Page',
                        'instagram_business_account' => [
                            'id' => 'ig-77',
                            'username' => 'shopig',
                            'profile_picture_url' => 'https://ig.example/pic.jpg',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/workspaces/{$workspace->id}/feeds/instagram/accounts?social_credential_id={$credential->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('accounts.0.instagram_business_account_id', 'ig-77')
            ->assertJsonPath('accounts.0.instagram_username', 'shopig')
            ->assertJsonPath('accounts.0.avatar_url', 'https://ig.example/pic.jpg');
    }

    /**
     * @return array{0: User, 1: Workspace, 2: SocialCredential}
     */
    private function workspaceWithCredential(string $provider): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Main',
            'owner_id' => $user->id,
        ]);
        $credential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'access_token' => "{$provider}-token",
            'expires_at' => now()->addHour(),
        ]);

        return [$user, $workspace, $credential];
    }
}
