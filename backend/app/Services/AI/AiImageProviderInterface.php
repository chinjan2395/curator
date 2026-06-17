<?php

namespace App\Services\AI;

interface AiImageProviderInterface
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $context
     * @return array{content: string, mime_type: string}
     */
    public function generateImage(string $prompt, array $context = []): array;
}
