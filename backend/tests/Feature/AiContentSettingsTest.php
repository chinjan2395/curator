<?php

namespace Tests\Feature;

use App\Models\AiProviderCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Content/caption generation half of AI settings: default provider/model and
 * BYOK keys, scoped under `kind=text`.
 */
class AiContentSettingsTest extends TestCase
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
            'services.ai.groq.api_key' => 'platform-groq-key',
            'services.ai.grok.api_key' => null,
        ]);

        $response = $this->getJson('/api/ai/settings/content')->assertOk();

        $providers = collect($response->json('data.providers'));

        $this->assertEqualsCanonicalizing(['groq', 'ollama', 'grok'], $providers->pluck('id')->all());

        $groq = $providers->firstWhere('id', 'groq');
        $this->assertTrue($groq['platform_configured']);
        $this->assertTrue($groq['available']);
        $this->assertSame('platform', $groq['key_source']);
        $this->assertFalse($groq['byok']['configured']);
        $this->assertNotEmpty($groq['models']);

        $ollama = $providers->firstWhere('id', 'ollama');
        $this->assertTrue($ollama['platform_configured']);
        $this->assertTrue($ollama['available']);
        $this->assertSame('platform', $ollama['key_source']);
    }

    public function test_defaults_can_be_saved_and_cleared(): void
    {
        $this->putJson('/api/ai/settings/content', [
            'default_provider' => 'groq',
            'default_model' => 'openai/gpt-oss-20b',
        ])->assertOk()
            ->assertJsonPath('data.default_provider', 'groq')
            ->assertJsonPath('data.default_model', 'openai/gpt-oss-20b');

        $this->assertSame('groq', $this->user->fresh()->ai_content_settings['default_provider']);
        $this->assertSame('openai/gpt-oss-20b', $this->user->fresh()->ai_content_settings['default_model']);

        $this->putJson('/api/ai/settings/content', ['default_provider' => null])
            ->assertOk()
            ->assertJsonPath('data.default_provider', null)
            ->assertJsonPath('data.default_model', 'openai/gpt-oss-20b');

        $this->putJson('/api/ai/settings/content', ['default_model' => null])
            ->assertOk()
            ->assertJsonPath('data.default_model', null);

        $this->assertSame([], array_intersect_key(
            $this->user->fresh()->ai_content_settings ?? [],
            ['default_provider' => null, 'default_model' => null],
        ));
    }

    public function test_unknown_default_provider_is_rejected(): void
    {
        $this->putJson('/api/ai/settings/content', ['default_provider' => 'chatgpt'])
            ->assertStatus(422);

        // The stub is not selectable.
        $this->putJson('/api/ai/settings/content', ['default_provider' => 'stub'])
            ->assertStatus(422);
    }

    public function test_unknown_default_model_is_rejected(): void
    {
        $this->putJson('/api/ai/settings/content', ['default_model' => 'not-a-real-model'])
            ->assertStatus(422);
    }

    public function test_a_user_can_store_a_groq_key_and_it_is_never_returned(): void
    {
        $response = $this->putJson('/api/ai/providers/text/groq/key', [
            'api_key' => 'gsk-my-own-key-4321',
        ])->assertOk();

        $this->assertStringNotContainsString('gsk-my-own-key-4321', $response->getContent());

        $groq = collect($response->json('data.providers'))->firstWhere('id', 'groq');
        $this->assertTrue($groq['byok']['configured']);
        $this->assertSame('4321', $groq['byok']['last_four']);
        $this->assertSame('byok', $groq['key_source']);
        $this->assertTrue($groq['available']);

        $stored = AiProviderCredential::where('user_id', $this->user->id)->where('kind', 'text')->firstOrFail();
        $this->assertSame('groq', $stored->provider);
        $this->assertSame('gsk-my-own-key-4321', $stored->api_key);
    }

    public function test_a_groq_key_can_be_removed(): void
    {
        config(['services.ai.groq.api_key' => null]);

        $this->putJson('/api/ai/providers/text/groq/key', ['api_key' => 'gsk-key-5555'])->assertOk();

        $response = $this->deleteJson('/api/ai/providers/text/groq/key')->assertOk();

        $groq = collect($response->json('data.providers'))->firstWhere('id', 'groq');
        $this->assertFalse($groq['byok']['configured']);
        $this->assertFalse($groq['available']);

        $this->assertSame(0, AiProviderCredential::where('user_id', $this->user->id)->where('kind', 'text')->count());
    }

    public function test_ollama_has_no_byok_and_rejects_a_stored_key(): void
    {
        $this->putJson('/api/ai/providers/text/ollama/key', ['api_key' => 'whatever-key'])
            ->assertStatus(404);

        $this->deleteJson('/api/ai/providers/text/ollama/key')
            ->assertStatus(404);
    }

    public function test_unknown_provider_key_is_rejected(): void
    {
        $this->putJson('/api/ai/providers/text/chatgpt/key', ['api_key' => 'whatever-key'])
            ->assertStatus(404);

        // The stub takes no credentials either.
        $this->putJson('/api/ai/providers/text/stub/key', ['api_key' => 'whatever-key'])
            ->assertStatus(404);
    }

    public function test_unknown_kind_is_rejected(): void
    {
        $this->putJson('/api/ai/providers/audio/groq/key', ['api_key' => 'whatever-key'])
            ->assertStatus(422);
    }

    public function test_image_and_text_keys_for_the_same_provider_name_do_not_cross_resolve(): void
    {
        // Not a realistic overlap (provider ids differ between image/text registries
        // today) but the resolver must still be strictly kind-scoped.
        AiProviderCredential::create([
            'user_id' => $this->user->id,
            'kind' => 'image',
            'provider' => 'groq',
            'api_key' => 'sk-image-side-key',
        ]);

        $response = $this->getJson('/api/ai/settings/content')->assertOk();

        $groq = collect($response->json('data.providers'))->firstWhere('id', 'groq');
        $this->assertFalse($groq['byok']['configured']);
    }

    public function test_content_keys_are_scoped_to_the_signed_in_user(): void
    {
        config(['services.ai.groq.api_key' => null]);

        $other = User::factory()->create();
        AiProviderCredential::create([
            'user_id' => $other->id,
            'kind' => 'text',
            'provider' => 'groq',
            'api_key' => 'gsk-other-users-key',
        ]);

        $response = $this->getJson('/api/ai/settings/content')->assertOk();

        $groq = collect($response->json('data.providers'))->firstWhere('id', 'groq');
        $this->assertFalse($groq['byok']['configured']);
        $this->assertFalse($groq['available']);
    }

    public function test_content_settings_require_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/ai/settings/content')->assertUnauthorized();
    }
}
