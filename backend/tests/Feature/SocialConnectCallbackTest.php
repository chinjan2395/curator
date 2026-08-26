<?php

namespace Tests\Feature;

use App\Models\OAuthAppConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialConnectCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_facebook_rate_limit_redirects_with_friendly_message_and_no_leaked_token(): void
    {
        $user = User::factory()->create();
        OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'user_id' => null,
            'provider' => 'facebook',
            'client_id' => 'shared-fb-id',
            'client_secret' => 'shared-fb-secret',
            'redirect_uri' => 'https://example.com/api/social/callback/facebook',
        ]);

        $rawGraphError = 'Client error: `GET https://graph.facebook.com/v23.0/me?access_token=EAF9SECRETTOKEN123&appsecret_proof=abc123def456` '
            .'resulted in a `403 Forbidden` response: '
            .'{"error":{"message":"(#4) Application request limit reached","type":"OAuthException","is_transient":true,"code":4}}';

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->once()->andThrow(new \RuntimeException($rawGraphError));
        Socialite::shouldReceive('driver')->with('facebook')->andReturn($driver);

        $state = Crypt::encryptString(json_encode(['user_id' => $user->id, 'provider' => 'facebook']));

        $response = $this->get('/api/social/callback/facebook?state='.urlencode($state));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/credentials?error=oauth_failed', $location);
        $this->assertStringNotContainsString('EAF9SECRETTOKEN123', $location);
        $this->assertStringNotContainsString('appsecret_proof', $location);
        $this->assertStringNotContainsString('graph.facebook.com', $location);

        $query = parse_url($location, PHP_URL_QUERY);
        parse_str((string) $query, $params);
        $this->assertSame('This provider is temporarily rate-limiting requests. Please wait a few minutes and try again.', $params['message']);
    }
}
