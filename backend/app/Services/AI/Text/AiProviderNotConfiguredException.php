<?php

namespace App\Services\AI\Text;

use App\Support\AiTextProviders;
use RuntimeException;

class AiProviderNotConfiguredException extends RuntimeException
{
    public static function for(string $provider): self
    {
        return new self(sprintf(
            '%s has no API key. Add your own key in AI Settings, or pick another provider.',
            AiTextProviders::label($provider),
        ));
    }
}
