<?php

namespace App\Support;

/**
 * Registry of AI image-generation providers.
 *
 * Single source of truth for validation rules, the AI settings payload, capabilities,
 * and the provider factory. Adding a provider should mean adding one entry here.
 *
 * Provider docs:
 * - OpenAI GPT Image: https://platform.openai.com/docs/api-reference/images
 * - Black Forest Labs FLUX: https://docs.bfl.ai/
 * - Google Gemini Image: https://ai.google.dev/gemini-api/docs/image-generation
 * - xAI Grok Image: https://docs.x.ai/
 */
class AiImageProviders
{
    public const STUB = 'stub';

    public const OPENAI = 'openai';

    public const FLUX = 'flux';

    public const GEMINI = 'gemini';

    public const GROK = 'grok';

    /**
     * @return array<string, array{
     *     id: string,
     *     label: string,
     *     supports_reference: bool,
     *     byok: bool,
     *     sizes: list<string>,
     *     models: list<array{id: string, label: string}>,
     *     default_model: string|null,
     *     config_path: string|null,
     * }>
     */
    public static function all(): array
    {
        return [
            self::OPENAI => [
                'id' => self::OPENAI,
                'label' => 'OpenAI GPT Image',
                'supports_reference' => true,
                'byok' => true,
                'sizes' => ['1024x1024', '1024x1536', '1536x1024'],
                'models' => [
                    ['id' => 'gpt-image-1', 'label' => 'GPT Image 1'],
                ],
                'default_model' => 'gpt-image-1',
                'config_path' => 'services.ai.image.openai',
            ],
            self::FLUX => [
                'id' => self::FLUX,
                'label' => 'FLUX (Black Forest Labs)',
                'supports_reference' => true,
                'byok' => true,
                'sizes' => ['1024x1024', '1024x1536', '1536x1024'],
                'models' => [
                    ['id' => 'flux-kontext-pro', 'label' => 'FLUX Kontext Pro'],
                    ['id' => 'flux-kontext-max', 'label' => 'FLUX Kontext Max'],
                    ['id' => 'flux-pro-1.1', 'label' => 'FLUX Pro 1.1'],
                ],
                'default_model' => 'flux-kontext-pro',
                'config_path' => 'services.ai.image.flux',
            ],
            self::GEMINI => [
                'id' => self::GEMINI,
                'label' => 'Google Gemini Image',
                'supports_reference' => true,
                'byok' => true,
                'sizes' => ['1024x1024', '1024x1536', '1536x1024'],
                'models' => [
                    ['id' => 'gemini-2.5-flash-image', 'label' => 'Gemini 2.5 Flash Image'],
                ],
                'default_model' => 'gemini-2.5-flash-image',
                'config_path' => 'services.ai.image.gemini',
            ],
            self::GROK => [
                'id' => self::GROK,
                'label' => 'xAI Grok Image',
                'supports_reference' => true,
                'byok' => true,
                // xAI does not expose fixed pixel size strings for image generations;
                // the union-of-all-sizes validation tolerates a provider contributing none.
                'sizes' => [],
                'models' => [
                    ['id' => 'grok-imagine-image-2.0', 'label' => 'Grok Imagine Image 2.0'],
                ],
                'default_model' => 'grok-imagine-image-2.0',
                'config_path' => 'services.ai.image.grok',
            ],
            self::STUB => [
                'id' => self::STUB,
                'label' => 'Stub (offline placeholder)',
                'supports_reference' => true,
                'byok' => false,
                'sizes' => ['1024x1024'],
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

    public static function supportsReference(string $provider): bool
    {
        return (bool) (self::find($provider)['supports_reference'] ?? false);
    }

    /** @return list<string> */
    public static function sizes(string $provider): array
    {
        return self::find($provider)['sizes'] ?? [];
    }

    /**
     * Every size any provider accepts — used to validate a stored default that may
     * outlive a change of provider.
     *
     * @return list<string>
     */
    public static function allSizes(): array
    {
        $sizes = [];
        foreach (self::all() as $spec) {
            foreach ($spec['sizes'] as $size) {
                $sizes[$size] = true;
            }
        }

        return array_keys($sizes);
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
     * The platform (env) API key for a provider, or null when it is not configured.
     */
    public static function platformKey(string $provider): ?string
    {
        $path = self::find($provider)['config_path'] ?? null;
        if ($path === null) {
            return null;
        }

        $key = config($path.'.api_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public static function platformConfigured(string $provider): bool
    {
        // The stub needs no credentials, so it is always available.
        return $provider === self::STUB || self::platformKey($provider) !== null;
    }

    public static function model(string $provider): ?string
    {
        $path = self::find($provider)['config_path'] ?? null;
        if ($path === null) {
            return null;
        }

        $model = config($path.'.model');

        return is_string($model) && $model !== '' ? $model : null;
    }
}
