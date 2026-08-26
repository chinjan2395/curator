<?php

namespace App\Support;

/**
 * Registry of AI text/caption-generation providers.
 *
 * Single source of truth for validation rules, the AI settings payload, and the
 * provider factory. Adding a provider should mean adding one entry here.
 *
 * Provider docs:
 * - Groq: https://console.groq.com/docs/models
 * - Ollama: https://ollama.com/library
 * - xAI Grok: https://docs.x.ai/
 */
class AiTextProviders
{
    public const STUB = 'stub';

    public const GROQ = 'groq';

    public const OLLAMA = 'ollama';

    public const GROK = 'grok';

    /**
     * @return array<string, array{
     *     id: string,
     *     label: string,
     *     byok: bool,
     *     models: list<array{id: string, label: string}>,
     *     default_model: string|null,
     *     config_path: string|null,
     * }>
     */
    public static function all(): array
    {
        return [
            self::GROQ => [
                'id' => self::GROQ,
                'label' => 'Groq',
                'byok' => true,
                'models' => [
                    ['id' => 'openai/gpt-oss-120b', 'label' => 'GPT-OSS 120B (default)'],
                    ['id' => 'openai/gpt-oss-20b', 'label' => 'GPT-OSS 20B'],
                    ['id' => 'llama-3.1-8b-instant', 'label' => 'Llama 3.1 8B Instant'],
                    ['id' => 'moonshotai/kimi-k2-instruct', 'label' => 'Kimi K2 Instruct'],
                ],
                'default_model' => 'openai/gpt-oss-120b',
                'config_path' => 'services.ai.groq',
            ],
            self::OLLAMA => [
                'id' => self::OLLAMA,
                'label' => 'Ollama (local)',
                'byok' => false,
                'models' => [
                    ['id' => 'llama3.2', 'label' => 'Llama 3.2 (default)'],
                ],
                'default_model' => 'llama3.2',
                'config_path' => 'services.ai.ollama',
            ],
            self::GROK => [
                'id' => self::GROK,
                'label' => 'xAI Grok',
                'byok' => true,
                'models' => [
                    ['id' => 'grok-4-0709', 'label' => 'Grok 4 (default)'],
                ],
                'default_model' => 'grok-4-0709',
                'config_path' => 'services.ai.grok',
            ],
            self::STUB => [
                'id' => self::STUB,
                'label' => 'Stub (offline placeholder)',
                'byok' => false,
                'models' => [],
                'default_model' => null,
                'config_path' => null,
            ],
        ];
    }

    /**
     * Provider ids a user may select, excluding the offline stub.
     *
     * @return list<string>
     */
    public static function selectableIds(): array
    {
        return array_values(array_keys(array_filter(
            self::all(),
            static fn (array $spec): bool => $spec['id'] !== self::STUB,
        )));
    }

    /**
     * Every known provider id, including the stub.
     *
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_values(array_keys(self::all()));
    }

    /** @return array<string, mixed>|null */
    public static function find(?string $provider): ?array
    {
        if ($provider === null) {
            return null;
        }

        return self::all()[strtolower(trim($provider))] ?? null;
    }

    public static function exists(?string $provider): bool
    {
        return self::find($provider) !== null;
    }

    public static function label(string $provider): string
    {
        return self::find($provider)['label'] ?? $provider;
    }

    /** @return list<array{id: string, label: string}> */
    public static function models(string $provider): array
    {
        return self::find($provider)['models'] ?? [];
    }

    /** @return list<string> */
    public static function modelIds(string $provider): array
    {
        return array_values(array_map(
            static fn (array $model): string => $model['id'],
            self::models($provider),
        ));
    }

    /**
     * Every model id any provider accepts — used to validate a stored default that may
     * outlive a change of provider.
     *
     * @return list<string>
     */
    public static function allModelIds(): array
    {
        $ids = [];
        foreach (self::all() as $spec) {
            foreach ($spec['models'] as $model) {
                $ids[$model['id']] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * The platform (env) API key for a provider, or null when it is not configured
     * or the provider has no key concept (Ollama, Stub).
     */
    public static function platformKey(string $provider): ?string
    {
        $spec = self::find($provider);
        if ($spec === null || ! $spec['byok'] || $spec['config_path'] === null) {
            return null;
        }

        $key = config($spec['config_path'].'.api_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public static function platformConfigured(string $provider): bool
    {
        $spec = self::find($provider);

        // The stub needs no credentials. Providers without a BYOK concept (Ollama)
        // are always considered configured — reachability is verified at call time.
        if ($spec === null) {
            return false;
        }

        if ($provider === self::STUB || ! $spec['byok']) {
            return true;
        }

        return self::platformKey($provider) !== null;
    }
}
