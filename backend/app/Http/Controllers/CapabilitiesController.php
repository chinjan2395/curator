<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Support\PlatformPublishSpecs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapabilitiesController extends Controller
{
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

    private function imageConfigured(string $driver): bool
    {
        return match ($driver) {
            'openai' => (bool) config('services.ai.image.openai.api_key'),
            default => $driver === 'stub',
        };
    }
}
