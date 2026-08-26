<?php

namespace App\Services\AI\Image;

/**
 * Everything a provider needs for one generation.
 *
 * `references` is a list so multi-reference support can be added without another
 * contract change; v1 only ever populates one.
 */
final class ImageGenerationRequest
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<ReferenceImage>  $references
     */
    public function __construct(
        public readonly string $prompt,
        public readonly array $context = [],
        public readonly array $references = [],
        public readonly ?string $size = null,
        public readonly ?string $model = null,
    ) {}

    public function hasReference(): bool
    {
        return $this->references !== [];
    }

    public function firstReference(): ?ReferenceImage
    {
        return $this->references[0] ?? null;
    }
}
