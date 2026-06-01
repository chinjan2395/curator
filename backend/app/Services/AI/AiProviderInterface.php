<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    public function name(): string;

    /** @param  array<string, mixed>  $context */
    public function generateText(string $prompt, array $context = []): string;
}
