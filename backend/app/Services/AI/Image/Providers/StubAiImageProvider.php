<?php

namespace App\Services\AI\Image\Providers;

use App\Services\AI\Image\AiImageProviderInterface;
use App\Services\AI\Image\GeneratedImage;
use App\Services\AI\Image\ImageGenerationRequest;
use App\Support\AiImageProviders;

/**
 * Offline placeholder so the whole flow works with no API keys configured.
 */
class StubAiImageProvider implements AiImageProviderInterface
{
    public function name(): string
    {
        return AiImageProviders::STUB;
    }

    public function supportsReference(): bool
    {
        return true;
    }

    public function generateImage(ImageGenerationRequest $request): GeneratedImage
    {
        if (extension_loaded('gd')) {
            return new GeneratedImage($this->render($request), 'image/png');
        }

        return new GeneratedImage((string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        ), 'image/png');
    }

    private function render(ImageGenerationRequest $request): string
    {
        $width = 512;
        $height = 512;
        $image = imagecreatetruecolor($width, $height);
        $seed = crc32($request->prompt);
        $background = imagecolorallocate($image, $seed & 0xFF, ($seed >> 8) & 0xFF, ($seed >> 16) & 0xFF);
        imagefill($image, 0, 0, $background);

        $textColor = imagecolorallocate($image, 255, 255, 255);
        $label = strtoupper(substr((string) ($request->context['platform'] ?? 'AI'), 0, 12));
        imagestring($image, 5, 16, (int) ($height / 2) - 8, $label, $textColor);

        // Make the reference visible in the output so tests (and a human eyeballing
        // local dev) can tell the reference actually reached the provider.
        $reference = $request->firstReference();
        if ($reference !== null) {
            imagestring($image, 3, 16, (int) ($height / 2) + 12, 'REF '.substr($reference->fileName, 0, 24), $textColor);

            $source = @imagecreatefromstring($reference->content);
            if ($source !== false) {
                $thumbSize = 128;
                imagecopyresampled(
                    $image, $source,
                    $width - $thumbSize - 16, $height - $thumbSize - 16,
                    0, 0,
                    $thumbSize, $thumbSize,
                    imagesx($source), imagesy($source),
                );
                imagedestroy($source);
            }
        }

        ob_start();
        imagepng($image);
        $content = (string) ob_get_clean();
        imagedestroy($image);

        return $content;
    }
}
