<?php

namespace App\Services\AI;

use App\Services\AI\Concerns\BuildsAiSystemPrompt;
use App\Services\AI\Concerns\CallsLlmApi;

class GroqAiProvider implements AiProviderInterface
{
    use BuildsAiSystemPrompt;
    use CallsLlmApi;

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $model = null,
    ) {}

    public function name(): string
    {
        return 'groq';
    }

    public function generateText(string $prompt, array $context = []): string
    {
        $apiKey = $this->apiKey ?? (string) config('services.ai.groq.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('GROQ_API_KEY is not configured.');
        }

        $system = $this->buildSystemPrompt($context);

        return $this->postJson('https://api.groq.com/openai/v1/chat/completions', [
            'model' => $this->model ?: config('services.ai.groq.model', 'openai/gpt-oss-120b'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            // gpt-oss is a reasoning model: max_tokens is deprecated, and a tight
            // budget can be spent entirely on chain-of-thought, returning empty content.
            'max_completion_tokens' => 2048,
            'reasoning_effort' => 'low',
        ], $apiKey);
    }
}
