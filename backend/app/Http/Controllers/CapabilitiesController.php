<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapabilitiesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $driver = config('services.ai.driver', 'stub');

        return ApiResponse::success([
            'ai' => [
                'driver' => $driver,
                'configured' => $this->aiConfigured($driver),
            ],
            'publish' => [
                'native' => [
                    'twitter' => ['enabled' => true, 'reason' => null],
                    'facebook' => ['enabled' => true, 'reason' => null],
                    'instagram' => ['enabled' => true, 'reason' => 'Requires public HTTPS image URL on content package'],
                    'youtube' => ['enabled' => false, 'reason' => 'Embed publishing only — use workspace publish'],
                    'tiktok' => ['enabled' => false, 'reason' => 'Native publisher not configured yet'],
                    'threads' => ['enabled' => false, 'reason' => 'Native publisher not configured yet'],
                ],
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
            'ollama' => (bool) config('services.ai.ollama.base_url'),
            default => false,
        };
    }
}
