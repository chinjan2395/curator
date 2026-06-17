<?php

namespace App\Services\AI;

use App\Services\AI\Concerns\BuildsAiSystemPrompt;
use App\Services\AI\Concerns\CallsLlmApi;

class OllamaAiProvider implements AiProviderInterface
{
    use BuildsAiSystemPrompt;
    use CallsLlmApi;

    public function name(): string
    {
        return 'ollama';
    }

    public function generateText(string $prompt, array $context = []): string
    {
        $baseUrl = rtrim((string) config('services.ai.ollama.url', 'http://127.0.0.1:11434'), '/');
        $model = (string) config('services.ai.ollama.model', 'llama3.2');

        $system = $this->buildSystemPrompt($context);

        return $this->postJson("{$baseUrl}/api/chat", [
            'model' => $model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);
    }
}
