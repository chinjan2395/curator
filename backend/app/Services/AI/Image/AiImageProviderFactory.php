<?php

namespace App\Services\AI\Image;

use App\Services\AI\Image\Providers\FluxImageProvider;
use App\Services\AI\Image\Providers\GeminiImageProvider;
use App\Services\AI\Image\Providers\GrokImageProvider;
use App\Services\AI\Image\Providers\OpenAiImageProvider;
use App\Services\AI\Image\Providers\StubAiImageProvider;
use App\Support\AiImageProviders;

/**
 * Builds a provider around an already-resolved key.
 *
 * Providers no longer read config for credentials, because the key depends on
 * which user is generating (BYOK) rather than on the process environment.
 */
class AiImageProviderFactory
{
    public function make(ResolvedImageKey $key): AiImageProviderInterface
    {
        if ($key->provider === AiImageProviders::STUB) {
            return new StubAiImageProvider;
        }

        $apiKey = $key->apiKey;
        if (! is_string($apiKey) || $apiKey === '') {
            throw AiProviderNotConfiguredException::for($key->provider);
        }

        return match ($key->provider) {
            AiImageProviders::OPENAI => new OpenAiImageProvider($apiKey),
            AiImageProviders::FLUX => new FluxImageProvider($apiKey),
            AiImageProviders::GEMINI => new GeminiImageProvider($apiKey),
            AiImageProviders::GROK => new GrokImageProvider($apiKey),
            default => throw AiProviderNotConfiguredException::for($key->provider),
        };
    }
}
