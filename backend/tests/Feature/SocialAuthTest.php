<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost:8000',
            'app.frontend_url' => 'http://localhost:5173',
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-secret',
            'services.facebook.client_id' => '123456789012345',
            'services.facebook.client_secret' => 'facebook-secret',
            'services.x.client_id' => '',
            'services.x.client_secret' => '',
            'services.github.client_id' => '',
            'services.github.client_secret' => '',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_google_callback_creates_user_and_redirects_with_token(): void
    {
        $this->mockSocialiteUser('google', $this->socialiteUser('99', 'Jane Doe', 'jane@example.com'));

        $response = $this->get('/api/auth/social/google/callback?code=oauth-code');

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://localhost:5173/auth/social/callback?token=', $location);
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'social_provider' => 'google',
            'social_provider_id' => '99',
        ]);
    }

    public function test_google_callback_links_existing_email_account(): void
    {
        $existing = User::factory()->create([
            'email' => 'jane@example.com',
            'social_provider' => null,
            'social_provider_id' => null,
        ]);

        $this->mockSocialiteUser('google', $this->socialiteUser('99', 'Jane Doe', 'jane@example.com'));

        $this->get('/api/auth/social/google/callback?code=oauth-code')
            ->assertRedirect();

        $existing->refresh();
        $this->assertSame('google', $existing->social_provider);
        $this->assertSame('99', $existing->social_provider_id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_callback_without_email_redirects_with_email_required(): void
    {
        $this->mockSocialiteUser('google', $this->socialiteUser('99', 'Jane Doe', null));

        $this->get('/api/auth/social/google/callback?code=oauth-code')
            ->assertRedirect('http://localhost:5173/login?error=email_required');
    }

    public function test_deactivated_user_cannot_sign_in_with_google(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'social_provider' => 'google',
            'social_provider_id' => '99',
            'deactivated_at' => now(),
        ]);

        $this->mockSocialiteUser('google', $this->socialiteUser('99', 'Jane Doe', 'jane@example.com'));

        $this->get('/api/auth/social/google/callback?code=oauth-code')
            ->assertRedirect('http://localhost:5173/login?error=account_deactivated');
    }

    public function test_provider_access_denied_is_not_a_generic_failure(): void
    {
        $this->get('/api/auth/social/google/callback?error=access_denied&error_description=User+denied')
            ->assertRedirect('http://localhost:5173/login?error=access_denied');
    }

    public function test_socialite_exception_redirects_with_mapped_error(): void
    {
        $this->mockSocialiteFailure('google', new \RuntimeException('Client error: invalid_grant'));

        $this->get('/api/auth/social/google/callback?code=oauth-code')
            ->assertRedirect('http://localhost:5173/login?error=token_exchange_failed');
    }

    public function test_missing_code_redirects_as_social_auth_failed(): void
    {
        $this->get('/api/auth/social/google/callback')
            ->assertRedirect('http://localhost:5173/login?error=social_auth_failed');
    }

    public function test_configured_providers_are_listed(): void
    {
        $this->getJson('/api/auth/social/providers')
            ->assertOk()
            ->assertJsonPath('providers', ['google', 'facebook']);
    }

    private function socialiteUser(string $id, string $name, ?string $email): SocialiteUser
    {
        return (new SocialiteUser)->map([
            'id' => $id,
            'nickname' => 'jane',
            'name' => $name,
            'email' => $email,
            'avatar' => null,
        ]);
    }

    private function mockSocialiteUser(string $provider, SocialiteUser $user): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->once()->andReturn($user);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    private function mockSocialiteFailure(string $provider, \Throwable $e): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->once()->andThrow($e);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }
}
