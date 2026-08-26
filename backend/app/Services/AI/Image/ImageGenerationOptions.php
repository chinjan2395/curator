<?php

namespace App\Services\AI\Image;

/**
 * What the user asked for on one generate-image call.
 *
 * `provider` and `size` are overrides; when null the user's saved AI settings
 * decide, then the platform default.
 */
final class ImageGenerationOptions
{
    public function __construct(
        public readonly ?string $instruction = null,
        public readonly ?string $provider = null,
        public readonly ?int $referenceAssetId = null,
        public readonly ?string $size = null,
        public readonly ?string $prompt = null,
        public readonly ?string $model = null,
    ) {}

    public static function fromInstruction(?string $instruction): self
    {
        return new self($instruction);
    }
}
