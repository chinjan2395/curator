<?php

namespace App\Services\AI\Image\Providers;

use App\Services\AI\Image\AiImageProviderInterface;
use App\Services\AI\Image\GeneratedImage;
use App\Services\AI\Image\ImageGenerationRequest;
use App\Support\AiImageProviders;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI images API.
 *
 * https://platform.openai.com/docs/api-reference/images
 *
 * Without a reference we call /images/generations; with one we call
 * /images/edits, which accepts the input image as multipart.
 */
class OpenAiImageProvider implements AiImageProviderInterface
{
    private const GENERATIONS_URL = 'https://api.openai.com/v1/images/generations';

    private const EDITS_URL = 'https://api.openai.com/v1/images/edits';

    public function __construct(private readonly string $apiKey) {}

    public function name(): string
    {
        return AiImageProviders::OPENAI;
    }

    public function supportsReference(): bool
    {
        return true;
    }

    public function generateImage(ImageGenerationRequest $request): GeneratedImage
    {
        $model = $request->model
            ?: (string) config('services.ai.image.openai.model', 'gpt-image-1');
        $size = $request->size
            ?: (string) config('services.ai.image.openai.size', '1024x1024');

        $response = $request->hasReference()
            ? $this->edit($request, $model, $size)
            : $this->generate($request, $model, $size);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI image generation failed: '.$this->errorMessage($response->json(), $response->body()));
        }

        return $this->decode($response->json('data.0.b64_json'));
    }

    private function generate(ImageGenerationRequest $request, string $model, string $size): Response
    {
        $payload = [
            'model' => $model,
            'prompt' => $request->prompt,
            'n' => 1,
            'size' => $size,
        ];

        // Only the dall-e-* models accept response_format; gpt-image-* always
        // returns b64_json and rejects the parameter outright.
        if ($this->isDallE($model)) {
            $payload['response_format'] = 'b64_json';
        }

        return $this->client()->asJson()->post(self::GENERATIONS_URL, $payload);
    }

    private function edit(ImageGenerationRequest $request, string $model, string $size): Response
    {
        $client = $this->client();

        foreach ($request->references as $reference) {
            $client = $client->attach(
                'image[]',
                $reference->content,
                $reference->fileName,
                ['Content-Type' => $reference->mimeType],
            );
        }

        $fields = [
            'model' => $model,
            'prompt' => $request->prompt,
            'n' => '1',
            'size' => $size,
        ];

        if ($this->isDallE($model)) {
            $fields['response_format'] = 'b64_json';
        }

        return $client->asMultipart()->post(self::EDITS_URL, $fields);
    }

    private function client(): PendingRequest
    {
        return Http::timeout(180)->acceptJson()->withToken($this->apiKey);
    }

    private function isDallE(string $model): bool
    {
        return str_starts_with($model, 'dall-e');
    }

    private function decode(mixed $encoded): GeneratedImage
    {
        if (! is_string($encoded) || $encoded === '') {
            throw new RuntimeException('OpenAI image generation returned no image data.');
        }

        $content = base64_decode($encoded, true);
        if ($content === false || $content === '') {
            throw new RuntimeException('OpenAI image generation returned invalid image data.');
        }

        return new GeneratedImage($content, 'image/png');
    }

    /** Prefer the provider's own error text over a raw body dump. */
    private function errorMessage(mixed $json, string $body): string
    {
        $message = data_get($json, 'error.message');

        return is_string($message) && $message !== '' ? $message : $body;
    }
}
