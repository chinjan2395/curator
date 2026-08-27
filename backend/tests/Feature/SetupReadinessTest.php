<?php

namespace Tests\Feature;

use App\Models\AiProviderCredential;
use App\Models\OAuthAppConfig;
use App\Models\SocialCredential;
use App\Models\User;
use App\Support\SetupRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SetupReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Start every case from "nothing is configured" so a developer's own
        // .env can never make these assertions pass by accident.
        config([
            'services.ai.groq.api_key' => null,
            'services.ai.grok.api_key' => null,
            'services.ai.ollama.url' => null,
            'services.ai.image.openai.api_key' => null,
            'services.ai.image.flux.api_key' => null,
            'services.ai.image.gemini.api_key' => null,
            'services.ai.image.grok.api_key' => null,
        ]);
    }

    private function actAs(?User $user = null): User
    {
        $user = $user ?: User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function readiness(): array
    {
        return $this->getJson('/api/setup/status')->assertOk()->json('data');
    }

    private function sharedFacebookApp(): OAuthAppConfig
    {
        return OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'user_id' => null,
            'provider' => 'facebook',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'redirect_uri' => 'https://example.test/callback',
        ]);
    }

    private function credential(User $user, string $tokenHealth, string $status = 'active'): SocialCredential
    {
        return SocialCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'facebook',
            'account_id' => 'acct-1',
            'account_label' => 'Test Page',
            'access_token' => 'token',
            'status' => $status,
            'token_health' => $tokenHealth,
        ]);
    }

    public function test_missing_oauth_app_blocks_the_whole_app(): void
    {
        $this->actAs();

        $data = $this->readiness();

        $this->assertSame(['oauth_app'], $data['blocking']);
        $this->assertFalse($data['ready']);
        $this->assertSame(SetupRequirements::STATE_MISSING, $data['requirements']['oauth_app']['state']);
        $this->assertSame(SetupRequirements::TIER_BLOCK, $data['requirements']['oauth_app']['tier']);
        $this->assertSame(SetupRequirements::SCOPE_PLATFORM, $data['requirements']['oauth_app']['scope']);
    }

    public function test_a_shared_oauth_app_clears_the_block(): void
    {
        $this->actAs();
        $this->sharedFacebookApp();

        $data = $this->readiness();

        $this->assertSame([], $data['blocking']);
        $this->assertSame(SetupRequirements::STATE_SATISFIED, $data['requirements']['oauth_app']['state']);
        $this->assertContains('facebook', $data['requirements']['oauth_app']['meta']['connectable_providers']);
    }

    public function test_oauth_app_is_only_fixable_by_an_admin(): void
    {
        $this->actAs(User::factory()->create());
        $this->assertFalse($this->readiness()['requirements']['oauth_app']['fixable_by_me']);

        $this->actAs(User::factory()->admin()->create());
        $this->assertTrue($this->readiness()['requirements']['oauth_app']['fixable_by_me']);
    }

    public function test_every_other_requirement_is_fixable_by_the_user(): void
    {
        $this->actAs();

        $requirements = $this->readiness()['requirements'];
        unset($requirements['oauth_app']);

        foreach ($requirements as $key => $requirement) {
            $this->assertTrue($requirement['fixable_by_me'], "{$key} should be self-serve");
        }
    }

    public function test_ai_provider_is_satisfied_by_a_platform_env_key(): void
    {
        $this->actAs();
        config(['services.ai.groq.api_key' => 'platform-key']);

        $requirement = $this->readiness()['requirements']['ai_provider'];

        $this->assertSame(SetupRequirements::STATE_SATISFIED, $requirement['state']);
        $this->assertSame('platform', $requirement['meta']['source']);
        $this->assertContains('groq', $requirement['meta']['available_providers']);
    }

    public function test_ai_provider_is_satisfied_by_a_byok_key(): void
    {
        $user = $this->actAs();
        AiProviderCredential::query()->create([
            'user_id' => $user->id,
            'kind' => 'text',
            'provider' => 'groq',
            'api_key' => 'user-supplied-key',
        ]);

        $requirement = $this->readiness()['requirements']['ai_provider'];

        $this->assertSame(SetupRequirements::STATE_SATISFIED, $requirement['state']);
        $this->assertSame('byok', $requirement['meta']['source']);
    }

    public function test_ai_provider_is_missing_with_no_key_anywhere_but_does_not_block(): void
    {
        $this->actAs();
        $this->sharedFacebookApp();

        $data = $this->readiness();

        $this->assertSame(SetupRequirements::STATE_MISSING, $data['requirements']['ai_provider']['state']);
        $this->assertSame(SetupRequirements::TIER_LOCK, $data['requirements']['ai_provider']['tier']);
        $this->assertSame([], $data['blocking']);
    }

    public function test_a_configured_ollama_url_counts_as_an_ai_provider(): void
    {
        $this->actAs();
        config(['services.ai.ollama.url' => 'http://ollama:11434']);

        $requirement = $this->readiness()['requirements']['ai_provider'];

        $this->assertSame(SetupRequirements::STATE_SATISFIED, $requirement['state']);
        $this->assertSame('local', $requirement['meta']['source']);
    }

    public function test_a_healthy_credential_satisfies_the_connected_account_requirement(): void
    {
        $user = $this->actAs();
        $this->credential($user, 'valid');

        $this->assertSame(
            SetupRequirements::STATE_SATISFIED,
            $this->readiness()['requirements']['social_credential']['state'],
        );
    }

    public function test_an_unverified_credential_still_counts_as_connected(): void
    {
        $user = $this->actAs();
        $this->credential($user, 'unknown');

        $this->assertSame(
            SetupRequirements::STATE_SATISFIED,
            $this->readiness()['requirements']['social_credential']['state'],
        );
    }

    public function test_an_expired_credential_reports_partial_not_satisfied(): void
    {
        $user = $this->actAs();
        $this->credential($user, 'expired');

        $requirement = $this->readiness()['requirements']['social_credential'];

        $this->assertSame(SetupRequirements::STATE_PARTIAL, $requirement['state']);
        $this->assertStringContainsString('reconnect', strtolower($requirement['detail']));
    }

    public function test_a_disconnected_credential_reports_partial_not_satisfied(): void
    {
        $user = $this->actAs();
        $this->credential($user, 'valid', 'disconnected');

        $this->assertSame(
            SetupRequirements::STATE_PARTIAL,
            $this->readiness()['requirements']['social_credential']['state'],
        );
    }

    public function test_every_registry_key_appears_in_the_payload(): void
    {
        $this->actAs();

        $requirements = $this->readiness()['requirements'];

        foreach (SetupRequirements::keys() as $key) {
            $this->assertArrayHasKey($key, $requirements);
        }
    }

    public function test_legacy_boolean_keys_are_still_returned(): void
    {
        $this->actAs(User::factory()->create(['is_onboarded' => false]));

        $this->getJson('/api/setup/status')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'is_onboarded',
                    'has_social_credentials',
                    'has_workspaces',
                    'has_feeds',
                    'has_synced_posts',
                    'has_campaigns',
                    'has_approved_packages',
                    'has_scheduled_posts',
                ],
            ])
            ->assertJsonPath('data.is_onboarded', false);
    }
}
