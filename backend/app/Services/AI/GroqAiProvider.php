<?php

namespace App\Services\AI;

use App\Services\AI\Concerns\BuildsAiSystemPrompt;
use App\Services\AI\Concerns\CallsLlmApi;

class GroqAiProvider implements AiProviderInterface
{
    use BuildsAiSystemPrompt;
    use CallsLlmApi;

    public function name(): string
    {
        return 'groq';
    }

    public function generateText(string $prompt, array $context = []): string
    {
        $apiKey = (string) config('services.ai.groq.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('GROQ_API_KEY is not configured.');
        }

        $system = $this->buildSystemPrompt($context);

        return $this->postJson('https://api.groq.com/openai/v1/chat/completions', [
            'model' => config('services.ai.groq.model', 'llama-3.3-70b-versatile'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024,
        ], $apiKey);
    }
}
