<?php

namespace Tests\Unit;

use App\Models\OAuthAppConfig;
use App\Models\SocialCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialCredentialFacebookRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_token_is_silently_renewed_before_expiry(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'access_token' => 'new-long-lived-token',
                'expires_in' => 5184000,
            ], 200),
        ]);

        $user = User::factory()->create();
        OAuthAppConfig::create([
            'user_id' => $user->id,
            'scope' => OAuthAppConfig::SCOPE_USER,
            'provider' => 'facebook',
            'client_id' => 'app-id',
            'client_secret' => 'app-secret',
        ]);

        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'account_id' => 'page-1',
            'access_token' => 'old-token-nearing-expiry',
            'expires_at' => now()->addDays(3),
            'status' => 'active',
        ]);

        $token = $credential->getValidAccessToken();

        $this->assertSame('new-long-lived-token', $token);
        $credential->refresh();
        $this->assertSame('new-long-lived-token', $credential->access_token);
        $this->assertTrue($credential->expires_at->isAfter(now()->addDays(50)));
    }

    public function test_facebook_token_not_yet_near_expiry_is_not_renewed(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'instagram',
            'account_id' => 'page-1',
            'access_token' => 'still-fresh-token',
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        $token = $credential->getValidAccessToken();

        $this->assertSame('still-fresh-token', $token);
        Http::assertNothingSent();
    }

    public function test_facebook_token_already_expired_and_exchange_fails_returns_null(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'expired']], 400),
        ]);

        $user = User::factory()->create();
        OAuthAppConfig::create([
            'user_id' => $user->id,
            'scope' => OAuthAppConfig::SCOPE_USER,
            'provider' => 'facebook',
            'client_id' => 'app-id',
            'client_secret' => 'app-secret',
        ]);

        $credential = SocialCredential::create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'account_id' => 'page-1',
            'access_token' => 'expired-token',
            'expires_at' => now()->subDay(),
            'status' => 'active',
        ]);

        $token = $credential->getValidAccessToken();

        $this->assertNull($token);
    }
}
