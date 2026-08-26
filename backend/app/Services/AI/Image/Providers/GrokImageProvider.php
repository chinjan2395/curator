<?php

namespace App\Services\AI\Image\Providers;

use App\Services\AI\Image\AiImageProviderInterface;
use App\Services\AI\Image\GeneratedImage;
use App\Services\AI\Image\ImageGenerationRequest;
use App\Support\AiImageProviders;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * xAI Grok images API.
 *
 * https://docs.x.ai/
 *
 * OpenAI-compatible schema, but xAI does not (yet) document a stable,
 * OpenAI-shaped edit/reference-image endpoint, so this provider only ever
 * calls /images/generations and never accepts a reference image.
 */
class GrokImageProvider implements AiImageProviderInterface
{
    private const GENERATIONS_URL = 'https://api.x.ai/v1/images/generations';

    public function __construct(private readonly string $apiKey) {}

    public function name(): string
    {
        return AiImageProviders::GROK;
    }

    public function supportsReference(): bool
    {
        return false;
    }

    public function generateImage(ImageGenerationRequest $request): GeneratedImage
    {
        $model = $request->model
            ?: (string) config('services.ai.image.grok.model', 'grok-2-image-1212');

        $response = Http::timeout(180)
            ->acceptJson()
            ->withToken($this->apiKey)
            ->asJson()
            ->post(self::GENERATIONS_URL, [
                'model' => $model,
                'prompt' => $request->prompt,
                'n' => 1,
                'response_format' => 'b64_json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Grok image generation failed: '.$this->errorMessage($response->json(), $response->body()));
        }

        return $this->decode($response->json('data.0.b64_json'));
    }

    private function decode(mixed $encoded): GeneratedImage
    {
        if (! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('Grok image generation returned no image data.');
        }

        $content = base64_decode($encoded, true);
        if ($content === false || $content === '') {
            throw new RuntimeException('Grok image generation returned invalid image data.');
        }

        // xAI's images endpoint returns JPEG, not PNG.
        return new GeneratedImage($content, 'image/jpeg');
    }

    /** Prefer the provider's own error text over a raw body dump. */
    private function errorMessage(mixed $json, string $body): string
    {
        $message = data_get($json, 'error.message');

        return is_string($message) && $message !== '' ? $message : $body;
    }
}
