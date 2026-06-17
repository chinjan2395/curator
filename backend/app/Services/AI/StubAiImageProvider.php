<?php

namespace App\Services\AI;

class StubAiImageProvider implements AiImageProviderInterface
{
    public function name(): string
    {
        return 'stub';
    }

    public function generateImage(string $prompt, array $context = []): array
    {
        if (extension_loaded('gd')) {
            $width = 512;
            $height = 512;
            $image = imagecreatetruecolor($width, $height);
            $seed = crc32($prompt);
            $background = imagecolorallocate($image, $seed & 0xFF, ($seed >> 8) & 0xFF, ($seed >> 16) & 0xFF);
            imagefill($image, 0, 0, $background);

            $textColor = imagecolorallocate($image, 255, 255, 255);
            $label = strtoupper(substr((string) ($context['platform'] ?? 'AI'), 0, 12));
            imagestring($image, 5, 16, (int) ($height / 2) - 8, $label, $textColor);

            ob_start();
            imagepng($image);
            $content = (string) ob_get_clean();
            imagedestroy($image);

            return [
                'content' => $content,
                'mime_type' => 'image/png',
            ];
        }

        return [
            'content' => (string) base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                true,
            ),
            'mime_type' => 'image/png',
        ];
    }
}
