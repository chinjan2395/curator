<?php

namespace App\Services\AI;

use App\Services\AI\Concerns\BuildsAiSystemPrompt;
use App\Services\AI\Concerns\CallsLlmApi;

/**
 * xAI Grok chat completions.
 *
 * https://docs.x.ai/
 *
 * OpenAI-SDK-compatible: same request/response shape as Groq/OpenAI chat
 * completions, so CallsLlmApi::postJson works unchanged.
 */
class GrokAiProvider implements AiProviderInterface
{
    use BuildsAiSystemPrompt;
    use CallsLlmApi;

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $model = null,
    ) {}

    public function name(): string
    {
        return 'grok';
    }

    public function generateText(string $prompt, array $context = []): string
    {
        $apiKey = $this->apiKey ?? (string) config('services.ai.grok.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('XAI_API_KEY is not configured.');
        }

        $system = $this->buildSystemPrompt($context);

        return $this->postJson('https://api.x.ai/v1/chat/completions', [
            'model' => $this->model ?: config('services.ai.grok.model', 'grok-4-0709'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 2048,
        ], $apiKey);
    }
}
