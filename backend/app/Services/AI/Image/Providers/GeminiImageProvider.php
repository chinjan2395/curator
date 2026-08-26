<?php

namespace App\Services\AI\Image\Providers;

use App\Services\AI\Image\AiImageProviderInterface;
use App\Services\AI\Image\GeneratedImage;
use App\Services\AI\Image\ImageGenerationRequest;
use App\Support\AiImageProviders;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Gemini native image generation ("Nano Banana").
 *
 * https://ai.google.dev/gemini-api/docs/image-generation
 *
 * Gemini is multimodal: the reference image travels as an inline_data part
 * alongside the text prompt in the same request, for both generate and edit.
 */
class GeminiImageProvider implements AiImageProviderInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(private readonly string $apiKey) {}

    public function name(): string
    {
        return AiImageProviders::GEMINI;
    }

    public function supportsReference(): bool
    {
        return true;
    }

    public function generateImage(ImageGenerationRequest $request): GeneratedImage
    {
        $model = $request->model
            ?: (string) config('services.ai.image.gemini.model', 'gemini-2.5-flash-image');

        $parts = [['text' => $request->prompt]];

        foreach ($request->references as $reference) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $reference->mimeType,
                    'data' => $reference->base64(),
                ],
            ];
        }

        $payload = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => array_filter([
                'responseModalities' => ['IMAGE'],
                'imageConfig' => $this->imageConfig($request->size),
            ], static fn ($value) => $value !== null),
        ];

        $response = Http::timeout(180)
            ->acceptJson()
            ->withHeaders(['x-goog-api-key' => $this->apiKey])
            ->asJson()
            ->post(self::BASE_URL.$model.':generateContent', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini image generation failed: '.$this->errorMessage($response->json(), $response->body()));
        }

        return $this->extractImage($response->json());
    }

    /** @param array<string, mixed>|null $json */
    private function extractImage(?array $json): GeneratedImage
    {
        $parts = data_get($json, 'candidates.0.content.parts');

        if (is_array($parts)) {
            foreach ($parts as $part) {
                // The REST API has used both spellings; accept either.
                $inline = $part['inline_data'] ?? $part['inlineData'] ?? null;
                if (! is_array($inline)) {
                    continue;
                }

                $encoded = $inline['data'] ?? null;
                if (! is_string($encoded) || $encoded === '') {
                    continue;
                }

                $content = base64_decode($encoded, true);
                if ($content === false || $content === '') {
                    continue;
                }

                $mime = $inline['mime_type'] ?? $inline['mimeType'] ?? 'image/png';

                return new GeneratedImage($content, is_string($mime) && $mime !== '' ? $mime : 'image/png');
            }
        }

        // A refusal comes back as a normal 200 with a finishReason and no image part.
        $reason = data_get($json, 'candidates.0.finishReason');
        if (is_string($reason) && $reason !== '' && $reason !== 'STOP') {
            throw new RuntimeException('Gemini returned no image ('.$reason.').');
        }

        throw new RuntimeException('Gemini image generation returned no image data.');
    }

    /** @return array<string, string>|null */
    private function imageConfig(?string $size): ?array
    {
        if ($size === null || ! str_contains($size, 'x')) {
            return null;
        }

        [$width, $height] = array_map('intval', explode('x', $size, 2));
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $divisor = $this->greatestCommonDivisor($width, $height);

        return ['aspectRatio' => ($width / $divisor).':'.($height / $divisor)];
    }

    private function greatestCommonDivisor(int $a, int $b): int
    {
        return $b === 0 ? max($a, 1) : $this->greatestCommonDivisor($b, $a % $b);
    }

    private function errorMessage(mixed $json, string $body): string
    {
        $message = data_get($json, 'error.message');

        return is_string($message) && $message !== '' ? $message : $body;
    }
}
