<?php

namespace Tests\Unit;

use App\Models\AiProviderCredential;
use App\Models\User;
use App\Services\AI\Image\AiProviderNotConfiguredException;
use App\Services\AI\Image\ImageKeyResolver;
use App\Services\AI\Image\ResolvedImageKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageKeyResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): ImageKeyResolver
    {
        return new ImageKeyResolver;
    }

    public function test_user_key_wins_over_platform_key(): void
    {
        config(['services.ai.image.openai.api_key' => 'platform-key']);

        $user = User::factory()->create();
        AiProviderCredential::create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'api_key' => 'user-key',
        ]);

        $resolved = $this->resolver()->resolve('openai', $user);

        $this->assertSame('user-key', $resolved->apiKey);
        $this->assertSame(ResolvedImageKey::SOURCE_BYOK, $resolved->source);
        $this->assertTrue($resolved->isByok());
    }

    public function test_platform_key_is_used_when_the_user_has_none(): void
    {
        config(['services.ai.image.openai.api_key' => 'platform-key']);

        $resolved = $this->resolver()->resolve('openai', User::factory()->create());

        $this->assertSame('platform-key', $resolved->apiKey);
        $this->assertSame(ResolvedImageKey::SOURCE_PLATFORM, $resolved->source);
    }

    public function test_another_users_key_is_never_used(): void
    {
        config(['services.ai.image.openai.api_key' => null]);

        $owner = User::factory()->create();
        AiProviderCredential::create([
            'user_id' => $owner->id,
            'provider' => 'openai',
            'api_key' => 'owner-key',
        ]);

        $this->expectException(AiProviderNotConfiguredException::class);

        $this->resolver()->resolve('openai', User::factory()->create());
    }

    public function test_missing_key_everywhere_throws_a_readable_error(): void
    {
        config(['services.ai.image.flux.api_key' => null]);

        try {
            $this->resolver()->resolve('flux', User::factory()->create());
            $this->fail('Expected AiProviderNotConfiguredException.');
        } catch (AiProviderNotConfiguredException $e) {
            $this->assertStringContainsString('FLUX', $e->getMessage());
            $this->assertStringContainsString('AI Settings', $e->getMessage());
        }
    }

    public function test_stub_never_needs_a_key(): void
    {
        $resolved = $this->resolver()->resolve('stub', User::factory()->create());

        $this->assertNull($resolved->apiKey);
        $this->assertSame(ResolvedImageKey::SOURCE_NONE, $resolved->source);
    }

    public function test_unknown_provider_is_rejected(): void
    {
        $this->expectException(AiProviderNotConfiguredException::class);

        $this->resolver()->resolve('midjourney', User::factory()->create());
    }

    public function test_availability_does_not_require_decrypting_the_key(): void
    {
        config(['services.ai.image.gemini.api_key' => null]);

        $user = User::factory()->create();
        $this->assertFalse($this->resolver()->isAvailable('gemini', $user));

        AiProviderCredential::create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'api_key' => 'gemini-user-key',
        ]);

        $this->assertTrue($this->resolver()->isAvailable('gemini', $user));
    }
}
