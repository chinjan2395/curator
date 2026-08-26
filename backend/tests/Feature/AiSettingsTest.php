<?php

namespace Tests\Feature;

use App\Models\AiProviderCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_settings_list_every_selectable_provider(): void
    {
        config([
            'services.ai.image.openai.api_key' => 'platform-key',
            'services.ai.image.flux.api_key' => null,
            'services.ai.image.gemini.api_key' => null,
            'services.ai.image.grok.api_key' => null,
        ]);

        $response = $this->getJson('/api/ai/settings')->assertOk();

        $providers = collect($response->json('data.providers'));

        $this->assertEqualsCanonicalizing(
            ['openai', 'flux', 'gemini', 'grok'],
            $providers->pluck('id')->all(),
        );

        $openai = $providers->firstWhere('id', 'openai');
        $this->assertTrue($openai['platform_configured']);
        $this->assertTrue($openai['available']);
        $this->assertSame('platform', $openai['key_source']);
        $this->assertFalse($openai['byok']['configured']);

        $flux = $providers->firstWhere('id', 'flux');
        $this->assertFalse($flux['available']);
        $this->assertNull($flux['key_source']);

        foreach ($providers as $provider) {
            $this->assertArrayHasKey('models', $provider);
            $this->assertIsArray($provider['models']);
        }
    }

    public function test_defaults_can_be_saved_and_cleared(): void
    {
        $this->putJson('/api/ai/settings', [
            'default_provider' => 'flux',
            'default_size' => '1024x1536',
        ])->assertOk()
            ->assertJsonPath('data.default_provider', 'flux')
            ->assertJsonPath('data.default_size', '1024x1536');

        $this->assertSame('flux', $this->user->fresh()->ai_image_settings['default_provider']);

        $this->putJson('/api/ai/settings', ['default_provider' => null])
            ->assertOk()
            ->assertJsonPath('data.default_provider', null)
            ->assertJsonPath('data.default_size', '1024x1536');
    }

    public function test_unknown_default_provider_is_rejected(): void
    {
        $this->putJson('/api/ai/settings', ['default_provider' => 'midjourney'])
            ->assertStatus(422);

        $this->putJson('/api/ai/settings', ['default_provider' => 'stub'])
            ->assertStatus(422);
    }

    public function test_default_model_can_be_saved_and_cleared(): void
    {
        $this->putJson('/api/ai/settings', [
            'default_provider' => 'flux',
            'default_model' => 'flux-kontext-max',
        ])->assertOk()
            ->assertJsonPath('data.default_model', 'flux-kontext-max');

        $this->assertSame('flux-kontext-max', $this->user->fresh()->ai_image_settings['default_model']);

        $this->putJson('/api/ai/settings', ['default_model' => null])
            ->assertOk()
            ->assertJsonPath('data.default_model', null);

        $this->assertArrayNotHasKey('default_model', $this->user->fresh()->ai_image_settings ?? []);
    }

    public function test_unknown_default_model_is_rejected(): void
    {
        $this->putJson('/api/ai/settings', ['default_model' => 'not-a-real-model'])
            ->assertStatus(422);
    }

    public function test_a_user_can_store_a_key_and_it_is_never_returned(): void
    {
        $response = $this->putJson('/api/ai/providers/image/openai/key', [
            'api_key' => 'sk-my-own-key-4321',
        ])->assertOk();

        $this->assertStringNotContainsString('sk-my-own-key-4321', $response->getContent());

        $openai = collect($response->json('data.providers'))->firstWhere('id', 'openai');
        $this->assertTrue($openai['byok']['configured']);
        $this->assertSame('4321', $openai['byok']['last_four']);
        $this->assertSame('byok', $openai['key_source']);
        $this->assertTrue($openai['available']);

        $stored = AiProviderCredential::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('sk-my-own-key-4321', $stored->api_key);
    }

    public function test_storing_a_key_twice_replaces_it(): void
    {
        $this->putJson('/api/ai/providers/image/openai/key', ['api_key' => 'sk-first-key-1111'])->assertOk();
        $this->putJson('/api/ai/providers/image/openai/key', ['api_key' => 'sk-second-key-2222'])->assertOk();

        $this->assertSame(1, AiProviderCredential::where('user_id', $this->user->id)->count());
        $this->assertSame(
            'sk-second-key-2222',
            AiProviderCredential::where('user_id', $this->user->id)->first()->api_key,
        );
    }

    public function test_a_key_can_be_removed(): void
    {
        config(['services.ai.image.flux.api_key' => null]);

        $this->putJson('/api/ai/providers/image/flux/key', ['api_key' => 'bfl-key-5555'])->assertOk();

        $response = $this->deleteJson('/api/ai/providers/image/flux/key')->assertOk();

        $flux = collect($response->json('data.providers'))->firstWhere('id', 'flux');
        $this->assertFalse($flux['byok']['configured']);
        $this->assertFalse($flux['available']);

        $this->assertSame(0, AiProviderCredential::where('user_id', $this->user->id)->count());
    }

    public function test_keys_are_scoped_to_the_signed_in_user(): void
    {
        config(['services.ai.image.openai.api_key' => null]);

        $other = User::factory()->create();
        AiProviderCredential::create([
            'user_id' => $other->id,
            'provider' => 'openai',
            'api_key' => 'sk-other-users-key',
        ]);

        $response = $this->getJson('/api/ai/settings')->assertOk();

        $openai = collect($response->json('data.providers'))->firstWhere('id', 'openai');
        $this->assertFalse($openai['byok']['configured']);
        $this->assertFalse($openai['available']);
    }

    public function test_unknown_provider_key_is_rejected(): void
    {
        $this->putJson('/api/ai/providers/image/midjourney/key', ['api_key' => 'whatever-key'])
            ->assertStatus(404);

        // The stub takes no credentials, so it must not accept a key either.
        $this->putJson('/api/ai/providers/image/stub/key', ['api_key' => 'whatever-key'])
            ->assertStatus(404);
    }

    public function test_settings_require_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/ai/settings')->assertUnauthorized();
    }

    public function test_capabilities_expose_per_provider_availability(): void
    {
        config([
            'services.ai.image.openai.api_key' => 'platform-key',
            'services.ai.image.flux.api_key' => null,
            'services.ai.image.gemini.api_key' => null,
        ]);

        $providers = collect($this->getJson('/api/capabilities')->assertOk()
            ->json('data.ai.image.providers'));

        $this->assertTrue($providers->firstWhere('id', 'openai')['available']);
        $this->assertFalse($providers->firstWhere('id', 'flux')['available']);
    }
}
