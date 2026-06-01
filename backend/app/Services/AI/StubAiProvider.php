<?php

namespace App\Services\AI;

class StubAiProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'stub';
    }

    public function generateText(string $prompt, array $context = []): string
    {
        $campaign = $context['campaign_name'] ?? 'your campaign';
        $platform = $context['platform'] ?? 'social';

        return "AI draft for {$campaign} on {$platform}: {$prompt}";
    }
}
