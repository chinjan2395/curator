<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\AiProviderCredential;
use App\Models\User;
use App\Services\AI\Image\ImageKeyResolver;
use App\Services\AI\Text\TextKeyResolver;
use App\Support\ActivityLogger;
use App\Support\AiImageProviders;
use App\Support\AiTextProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Per-user AI settings: default provider/size/model, plus the user's own
 * provider API keys (BYOK) — for both image generation and content/caption
 * generation.
 *
 * Stored keys are never returned — only whether one exists and its last four
 * characters, so the UI can render a mask without the plaintext leaving the server.
 */
class AiSettingsController extends Controller
{
    private const KINDS = ['image', 'text'];

    public function __construct(
        private readonly ImageKeyResolver $keys,
        private readonly TextKeyResolver $textKeys,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->payload($user));
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'default_provider' => ['nullable', 'string', Rule::in(AiImageProviders::selectableIds())],
            'default_size' => ['nullable', 'string', Rule::in(AiImageProviders::allSizes())],
            'default_model' => ['nullable', 'string', Rule::in(AiImageProviders::allModelIds())],
        ]);

        $settings = is_array($user->ai_image_settings) ? $user->ai_image_settings : [];

        foreach (['default_provider', 'default_size', 'default_model'] as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            // An explicit null clears the preference and falls back to the platform default.
            if ($validated[$key] === null) {
                unset($settings[$key]);

                continue;
            }
            $settings[$key] = $validated[$key];
        }

        $user->ai_image_settings = $settings === [] ? null : $settings;
        $user->save();

        return ApiResponse::success($this->payload($user), 'AI settings saved.');
    }

    public function showContent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->contentPayload($user));
    }

    public function updateContent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'default_provider' => ['nullable', 'string', Rule::in(AiTextProviders::selectableIds())],
            'default_model' => ['nullable', 'string', Rule::in(AiTextProviders::allModelIds())],
        ]);

        $settings = is_array($user->ai_content_settings) ? $user->ai_content_settings : [];

        foreach (['default_provider', 'default_model'] as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            // An explicit null clears the preference and falls back to the platform default.
            if ($validated[$key] === null) {
                unset($settings[$key]);

                continue;
            }
            $settings[$key] = $validated[$key];
        }

        $user->ai_content_settings = $settings === [] ? null : $settings;
        $user->save();

        return ApiResponse::success($this->contentPayload($user), 'AI settings saved.');
    }

    public function storeKey(Request $request, string $kind, string $provider): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! in_array($kind, self::KINDS, true)) {
            return ApiResponse::error('Unknown AI provider kind.', null, 422);
        }

        if (! $this->isByokProvider($kind, $provider)) {
            return ApiResponse::error('Unknown AI '.$kind.' provider.', null, 404);
        }

        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        AiProviderCredential::updateOrCreate(
            ['user_id' => $user->id, 'kind' => $kind, 'provider' => $provider],
            ['api_key' => trim($validated['api_key'])],
        );

        $label = $this->label($kind, $provider);

        ActivityLogger::log(
            $user,
            'ai_settings.key_saved',
            'Saved a personal API key for '.$label,
        );

        return ApiResponse::success(
            $kind === 'text' ? $this->contentPayload($user) : $this->payload($user),
            $label.' key saved.',
        );
    }

    public function destroyKey(Request $request, string $kind, string $provider): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! in_array($kind, self::KINDS, true)) {
            return ApiResponse::error('Unknown AI provider kind.', null, 422);
        }

        if (! $this->isByokProvider($kind, $provider)) {
            return ApiResponse::error('Unknown AI '.$kind.' provider.', null, 404);
        }

        AiProviderCredential::query()
            ->where('user_id', $user->id)
            ->where('kind', $kind)
            ->where('provider', $provider)
            ->delete();

        $label = $this->label($kind, $provider);

        ActivityLogger::log(
            $user,
            'ai_settings.key_removed',
            'Removed the personal API key for '.$label,
        );

        return ApiResponse::success(
            $kind === 'text' ? $this->contentPayload($user) : $this->payload($user),
            $label.' key removed.',
        );
    }

    private function isByokProvider(string $kind, string $provider): bool
    {
        $spec = $kind === 'text' ? AiTextProviders::find($provider) : AiImageProviders::find($provider);

        return $spec !== null && ($spec['byok'] ?? false);
    }

    private function label(string $kind, string $provider): string
    {
        return $kind === 'text' ? AiTextProviders::label($provider) : AiImageProviders::label($provider);
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        $settings = is_array($user->ai_image_settings) ? $user->ai_image_settings : [];

        $providers = [];
        foreach (AiImageProviders::selectableIds() as $id) {
            $credential = $this->keys->credential($id, $user);
            $platformConfigured = AiImageProviders::platformConfigured($id);

            $providers[] = [
                'id' => $id,
                'label' => AiImageProviders::label($id),
                'supports_reference' => AiImageProviders::supportsReference($id),
                'sizes' => AiImageProviders::sizes($id),
                'models' => AiImageProviders::models($id),
                'platform_configured' => $platformConfigured,
                'byok' => [
                    'configured' => $credential !== null,
                    'last_four' => $credential?->key_last_four,
                    'updated_at' => $credential?->updated_at?->toIso8601String(),
                ],
                'available' => $credential !== null || $platformConfigured,
                // Which key a generation would actually use right now.
                'key_source' => $credential !== null
                    ? 'byok'
                    : ($platformConfigured ? 'platform' : null),
            ];
        }

        return [
            'default_provider' => $settings['default_provider'] ?? null,
            'default_size' => $settings['default_size'] ?? null,
            'default_model' => $settings['default_model'] ?? null,
            'fallback_provider' => config('services.ai.image.driver', AiImageProviders::STUB),
            'providers' => $providers,
        ];
    }

    /** @return array<string, mixed> */
    private function contentPayload(User $user): array
    {
        $settings = is_array($user->ai_content_settings) ? $user->ai_content_settings : [];

        $providers = [];
        foreach (AiTextProviders::selectableIds() as $id) {
            $credential = $this->textKeys->credential($id, $user);
            $platformConfigured = AiTextProviders::platformConfigured($id);

            $providers[] = [
                'id' => $id,
                'label' => AiTextProviders::label($id),
                'models' => AiTextProviders::models($id),
                'platform_configured' => $platformConfigured,
                'byok' => [
                    'configured' => $credential !== null,
                    'last_four' => $credential?->key_last_four,
                    'updated_at' => $credential?->updated_at?->toIso8601String(),
                ],
                'available' => $credential !== null || $platformConfigured,
                // Which key a generation would actually use right now.
                'key_source' => $credential !== null
                    ? 'byok'
                    : ($platformConfigured ? 'platform' : null),
            ];
        }

        return [
            'default_provider' => $settings['default_provider'] ?? null,
            'default_model' => $settings['default_model'] ?? null,
            'fallback_provider' => config('services.ai.driver', AiTextProviders::STUB),
            'providers' => $providers,
        ];
    }
}
