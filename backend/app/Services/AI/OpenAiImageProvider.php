<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiImageProvider implements AiImageProviderInterface
{
    public function name(): string
    {
        return 'openai';
    }

    public function generateImage(string $prompt, array $context = []): array
    {
        $apiKey = (string) config('services.ai.image.openai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured for image generation.');
        }

        $response = Http::timeout(120)
            ->acceptJson()
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => config('services.ai.image.openai.model', 'dall-e-3'),
                'prompt' => $prompt,
                'n' => 1,
                'size' => config('services.ai.image.openai.size', '1024x1024'),
                'response_format' => 'b64_json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI image generation failed: '.$response->body());
        }

        $encoded = $response->json('data.0.b64_json');
        if (! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('OpenAI image generation returned no image data.');
        }

        $content = base64_decode($encoded, true);
        if ($content === false || $content === '') {
            throw new RuntimeException('OpenAI image generation returned invalid image data.');
        }

        return [
            'content' => $content,
            'mime_type' => 'image/png',
        ];
    }
}
