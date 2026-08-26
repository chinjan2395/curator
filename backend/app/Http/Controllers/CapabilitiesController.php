<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\User;
use App\Services\AI\Image\ImageKeyResolver;
use App\Support\AiImageProviders;
use App\Support\PlatformPublishSpecs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapabilitiesController extends Controller
{
    public function __construct(private readonly ImageKeyResolver $keys) {}

    public function show(Request $request): JsonResponse
    {
        $driver = config('services.ai.driver', 'stub');
        $imageDriver = config('services.ai.image.driver', 'stub');

        return ApiResponse::success([
            'ai' => [
                'driver' => $driver,
                'configured' => $this->aiConfigured($driver),
                'image' => [
                    'driver' => $imageDriver,
                    'configured' => $this->imageConfigured($imageDriver),
                    'providers' => $this->imageProviders($request->user()),
                ],
            ],
            'publish' => [
                'native' => PlatformPublishSpecs::nativeMatrix(),
                'content_specs' => PlatformPublishSpecs::all(),
            ],
            'inbox' => [
                'sync_mode' => 'stub',
            ],
        ]);
    }

    private function aiConfigured(string $driver): bool
    {
        return match ($driver) {
            'groq' => (bool) config('services.ai.groq.api_key'),
            'ollama' => (bool) config('services.ai.ollama.url'),
            default => false,
        };
    }

    /**
     * Per-provider availability for the signed-in user, so the UI can gate the
     * generate action and point at AI Settings when nothing is usable.
     *
     * @return list<array<string, mixed>>
     */
    private function imageProviders(?User $user): array
    {
        $providers = [];

        foreach (AiImageProviders::selectableIds() as $id) {
            $providers[] = [
                'id' => $id,
                'label' => AiImageProviders::label($id),
                'supports_reference' => AiImageProviders::supportsReference($id),
                'sizes' => AiImageProviders::sizes($id),
                'available' => $this->keys->isAvailable($id, $user),
            ];
        }

        return $providers;
    }

    private function imageConfigured(string $driver): bool
    {
        return match ($driver) {
            'openai' => (bool) config('services.ai.image.openai.api_key'),
            default => $driver === 'stub',
        };
    }
}
