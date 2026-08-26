<?php

namespace App\Services\AI\Image;

use App\Support\AiImageProviders;
use RuntimeException;

class AiProviderNotConfiguredException extends RuntimeException
{
    public static function for(string $provider): self
    {
        return new self(sprintf(
            '%s has no API key. Add your own key in AI Settings, or pick another provider.',
            AiImageProviders::label($provider),
        ));
    }
}
