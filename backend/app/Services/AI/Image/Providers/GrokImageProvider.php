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
 * xAI exposes two endpoints: /images/generations for text-only prompts, and
 * /images/edits for reference-conditioned generation. Edits take the same
 * JSON body shape as generations, plus an inline base64 data URI for the
 * reference image(s) — there is no multipart upload, unlike OpenAI's edits
 * endpoint.
 */
class GrokImageProvider implements AiImageProviderInterface
{
    private const GENERATIONS_URL = 'https://api.x.ai/v1/images/generations';

    private const EDITS_URL = 'https://api.x.ai/v1/images/edits';

    public function __construct(private readonly string $apiKey) {}

    public function name(): string
    {
        return AiImageProviders::GROK;
    }

    public function supportsReference(): bool
    {
        return true;
    }

    public function generateImage(ImageGenerationRequest $request): GeneratedImage
    {
        $model = $request->model
            ?: (string) config('services.ai.image.grok.model', 'grok-imagine-image-2.0');

        if ($request->hasReference()) {
            return $this->edit($request, $model);
        }

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

    private function edit(ImageGenerationRequest $request, string $model): GeneratedImage
    {
        $payload = [
            'model' => $model,
            'prompt' => $request->prompt,
            'n' => 1,
            'response_format' => 'b64_json',
        ];

        if (count($request->references) > 1) {
            $payload['images'] = array_map(
                static fn ($reference) => ['url' => $reference->dataUri(), 'type' => 'image_url'],
                $request->references,
            );
        } else {
            $payload['image'] = ['url' => $request->firstReference()->dataUri(), 'type' => 'image_url'];
        }

        $response = Http::timeout(180)
            ->acceptJson()
            ->withToken($this->apiKey)
            ->asJson()
            ->post(self::EDITS_URL, $payload);

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

    /**
     * Prefer the provider's own error text over a raw body dump. xAI's errors
     * come back as a flat string under `error` (e.g. billing/permission
     * failures) rather than nested under `error.message` like OpenAI's shape,
     * so check both.
     */
    private function errorMessage(mixed $json, string $body): string
    {
        $nested = data_get($json, 'error.message');
        if (is_string($nested) && $nested !== '') {
            return $nested;
        }

        $flat = data_get($json, 'error');
        if (is_string($flat) && $flat !== '') {
            return $flat;
        }

        return $body;
    }
}
