<?php

namespace Tests\Feature;

use App\Models\OAuthAppConfig;
use App\Models\SocialCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthAppConfigScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_upsert_shared_config(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/oauth-app-configs', [
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'provider' => 'google',
            'client_id' => 'shared-client',
            'client_secret' => 'shared-secret',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('oauth_app_configs', [
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'provider' => 'google',
        ]);
    }

    public function test_admin_can_upsert_shared_config(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->postJson('/api/oauth-app-configs', [
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'provider' => 'google',
            'client_id' => 'shared-client',
            'client_secret' => 'shared-secret',
            'redirect_uri' => 'https://example.com/callback',
        ]);

        $response->assertCreated()->assertJson([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'provider' => 'google',
            'client_id' => 'shared-client',
        ]);

        $this->assertDatabaseHas('oauth_app_configs', [
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'provider' => 'google',
            'user_id' => null,
            'client_id' => 'shared-client',
        ]);
    }

    public function test_index_exposes_effective_scope_with_shared_fallback(): void
    {
        $user = User::factory()->create();
        OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'user_id' => null,
            'provider' => 'twitter',
            'client_id' => 'shared-twitter-id',
            'client_secret' => 'shared-twitter-secret',
            'redirect_uri' => 'https://example.com/callback',
        ]);

        $response = $this->actingAs($user)->getJson('/api/oauth-app-configs');

        $response->assertOk()->assertJsonFragment([
            'provider' => 'twitter',
            'effective_scope' => OAuthAppConfig::SCOPE_SHARED,
        ]);
    }

    public function test_regular_user_sees_youtube_connectable_from_shared_google_config(): void
    {
        $member = User::factory()->create();

        OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'user_id' => null,
            'provider' => 'google',
            'client_id' => 'shared-google-id',
            'client_secret' => 'shared-google-secret',
            'redirect_uri' => 'https://example.com/callback/youtube',
        ]);

        $response = $this->actingAs($member)->getJson('/api/oauth-app-configs');

        $response->assertOk()
            ->assertJsonPath('connectable_social_providers', function ($providers) {
                return in_array('youtube', $providers, true) && in_array('google', $providers, true);
            })
            ->assertJsonPath('items', function ($items) {
                $google = collect($items)->firstWhere('provider', 'google');

                return is_array($google)
                    && ($google['effective_scope'] ?? null) === OAuthAppConfig::SCOPE_SHARED;
            });
    }

    public function test_shared_legacy_youtube_provider_key_still_enables_youtube_connect(): void
    {
        $member = User::factory()->create();

        OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'user_id' => null,
            'provider' => 'youtube',
            'client_id' => 'legacy-youtube-id',
            'client_secret' => 'legacy-youtube-secret',
            'redirect_uri' => 'https://example.com/callback/youtube',
        ]);

        $response = $this->actingAs($member)->getJson('/api/oauth-app-configs');

        $response->assertOk()
            ->assertJsonFragment(['provider' => 'google'])
            ->assertJsonPath('connectable_social_providers', function ($providers) {
                return in_array('youtube', $providers, true);
            });
    }

    public function test_social_credential_refresh_uses_shared_fallback(): void
    {
        $user = User::factory()->create();
        OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'user_id' => null,
            'provider' => 'google',
            'client_id' => 'shared-google-id',
            'client_secret' => 'shared-google-secret',
            'redirect_uri' => 'https://example.com/callback',
        ]);
        $credential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'youtube',
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->subHour(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = $credential->fresh()->getValidAccessToken();
        $this->assertSame('new-access-token', $token);
    }

    public function test_social_credential_refresh_prefers_user_override_over_shared(): void
    {
        $user = User::factory()->create();
        OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'user_id' => null,
            'provider' => 'google',
            'client_id' => 'shared-google-id',
            'client_secret' => 'shared-google-secret',
        ]);
        OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_USER,
            'user_id' => $user->id,
            'provider' => 'google',
            'client_id' => 'user-google-id',
            'client_secret' => 'user-google-secret',
        ]);
        $credential = SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'youtube',
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->subHour(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $credential->fresh()->getValidAccessToken();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && ($request['client_id'] ?? null) === 'user-google-id';
        });
    }
}

