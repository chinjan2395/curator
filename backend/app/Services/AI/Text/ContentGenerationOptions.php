<?php

namespace App\Services\AI\Text;

/**
 * What the user asked for on one content/caption generation call.
 *
 * `provider` and `model` are overrides; when null the user's saved AI settings
 * decide, then the platform default.
 */
final class ContentGenerationOptions
{
    public function __construct(
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
    ) {}
}
