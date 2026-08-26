<?php

namespace App\Services\AI\Image;

interface AiImageProviderInterface
{
    public function name(): string;

    /** Whether this provider can use a reference image to condition the output. */
    public function supportsReference(): bool;

    public function generateImage(ImageGenerationRequest $request): GeneratedImage;
}
