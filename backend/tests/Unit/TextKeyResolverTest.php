<?php

namespace Tests\Unit;

use App\Models\AiProviderCredential;
use App\Models\User;
use App\Services\AI\Text\AiProviderNotConfiguredException;
use App\Services\AI\Text\ResolvedTextKey;
use App\Services\AI\Text\TextKeyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TextKeyResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): TextKeyResolver
    {
        return new TextKeyResolver;
    }

    public function test_user_key_wins_over_platform_key(): void
    {
        config(['services.ai.groq.api_key' => 'platform-key']);

        $user = User::factory()->create();
        AiProviderCredential::create([
            'user_id' => $user->id,
            'kind' => 'text',
            'provider' => 'groq',
            'api_key' => 'user-key',
        ]);

        $resolved = $this->resolver()->resolve('groq', $user);

        $this->assertSame('user-key', $resolved->apiKey);
        $this->assertSame(ResolvedTextKey::SOURCE_BYOK, $resolved->source);
        $this->assertTrue($resolved->isByok());
    }

    public function test_platform_key_is_used_when_the_user_has_none(): void
    {
        config(['services.ai.groq.api_key' => 'platform-key']);

        $resolved = $this->resolver()->resolve('groq', User::factory()->create());

        $this->assertSame('platform-key', $resolved->apiKey);
        $this->assertSame(ResolvedTextKey::SOURCE_PLATFORM, $resolved->source);
    }

    public function test_another_users_key_is_never_used(): void
    {
        config(['services.ai.groq.api_key' => null]);

        $owner = User::factory()->create();
        AiProviderCredential::create([
            'user_id' => $owner->id,
            'kind' => 'text',
            'provider' => 'groq',
            'api_key' => 'owner-key',
        ]);

        $this->expectException(AiProviderNotConfiguredException::class);

        $this->resolver()->resolve('groq', User::factory()->create());
    }

    public function test_an_image_kind_credential_never_resolves_for_text(): void
    {
        config(['services.ai.groq.api_key' => null]);

        $user = User::factory()->create();
        AiProviderCredential::create([
            'user_id' => $user->id,
            'kind' => 'image',
            'provider' => 'groq',
            'api_key' => 'image-scoped-key',
        ]);

        $this->expectException(AiProviderNotConfiguredException::class);

        $this->resolver()->resolve('groq', $user);
    }

    public function test_missing_key_everywhere_throws_a_readable_error(): void
    {
        config(['services.ai.groq.api_key' => null]);

        try {
            $this->resolver()->resolve('groq', User::factory()->create());
            $this->fail('Expected AiProviderNotConfiguredException.');
        } catch (AiProviderNotConfiguredException $e) {
            $this->assertStringContainsString('Groq', $e->getMessage());
            $this->assertStringContainsString('AI Settings', $e->getMessage());
        }
    }

    public function test_stub_never_needs_a_key(): void
    {
        $resolved = $this->resolver()->resolve('stub', User::factory()->create());

        $this->assertNull($resolved->apiKey);
        $this->assertSame(ResolvedTextKey::SOURCE_NONE, $resolved->source);
    }

    public function test_ollama_is_always_resolvable_without_a_key(): void
    {
        $resolved = $this->resolver()->resolve('ollama', User::factory()->create());

        $this->assertNull($resolved->apiKey);
        $this->assertSame(ResolvedTextKey::SOURCE_NONE, $resolved->source);
        $this->assertTrue($this->resolver()->isAvailable('ollama', User::factory()->create()));
        $this->assertTrue($this->resolver()->isAvailable('ollama', null));
    }

    public function test_unknown_provider_is_rejected(): void
    {
        $this->expectException(AiProviderNotConfiguredException::class);

        $this->resolver()->resolve('midjourney', User::factory()->create());
    }

    public function test_availability_does_not_require_decrypting_the_key(): void
    {
        config(['services.ai.groq.api_key' => null]);

        $user = User::factory()->create();
        $this->assertFalse($this->resolver()->isAvailable('groq', $user));

        AiProviderCredential::create([
            'user_id' => $user->id,
            'kind' => 'text',
            'provider' => 'groq',
            'api_key' => 'groq-user-key',
        ]);

        $this->assertTrue($this->resolver()->isAvailable('groq', $user));
    }
}
