<?php

namespace App\Services\AI\Image;

/**
 * An image supplied by the user to condition a generation.
 *
 * Holds raw binary — never log or serialize this object.
 */
final class ReferenceImage
{
    /** Mime types every v1 provider accepts as an input image. */
    public const ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];

    /** Providers reject large inputs; keep this in step with the upload validation. */
    public const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(
        public readonly string $content,
        public readonly string $mimeType,
        public readonly string $fileName,
    ) {}

    public function base64(): string
    {
        return base64_encode($this->content);
    }

    public function dataUri(): string
    {
        return 'data:'.$this->mimeType.';base64,'.$this->base64();
    }

    public static function isAllowedMimeType(?string $mimeType): bool
    {
        return in_array(strtolower((string) $mimeType), self::ALLOWED_MIME_TYPES, true);
    }

    /** Keep binary out of stack traces and dumps. */
    public function __debugInfo(): array
    {
        return [
            'mimeType' => $this->mimeType,
            'fileName' => $this->fileName,
            'bytes' => strlen($this->content),
        ];
    }
}
