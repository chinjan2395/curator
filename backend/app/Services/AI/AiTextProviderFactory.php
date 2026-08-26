<?php

namespace App\Services\AI;

use App\Services\AI\Text\ResolvedTextKey;
use App\Support\AiTextProviders;

/**
 * Builds a text provider around an already-resolved key.
 *
 * Providers no longer read config for credentials, because the key depends on
 * which user is generating (BYOK) rather than on the process environment.
 */
class AiTextProviderFactory
{
    public function make(ResolvedTextKey $key, ?string $model = null): AiProviderInterface
    {
        return match ($key->provider) {
            AiTextProviders::GROQ => new GroqAiProvider($key->apiKey, $model),
            AiTextProviders::OLLAMA => new OllamaAiProvider($model),
            AiTextProviders::GROK => new GrokAiProvider($key->apiKey, $model),
            default => new StubAiProvider,
        };
    }
}
