<?php

namespace App\Services\AI\Image;

/**
 * The binary a provider returned. Replaces the old array{content, mime_type}.
 */
final class GeneratedImage
{
    public function __construct(
        public readonly string $content,
        public readonly string $mimeType = 'image/png',
    ) {}

    public function extension(): string
    {
        return match (strtolower($this->mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    public function bytes(): int
    {
        return strlen($this->content);
    }

    /** Keep binary out of stack traces and dumps. */
    public function __debugInfo(): array
    {
        return [
            'mimeType' => $this->mimeType,
            'bytes' => $this->bytes(),
        ];
    }
}
