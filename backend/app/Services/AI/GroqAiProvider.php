<?php

namespace App\Services\AI;

use App\Services\AI\Concerns\CallsLlmApi;
use App\Services\AI\Concerns\FormatsAiPromptContext;

class GroqAiProvider implements AiProviderInterface
{
    use CallsLlmApi;
    use FormatsAiPromptContext;

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

    /** @param  array<string, mixed>  $context */
    private function buildSystemPrompt(array $context): string
    {
        $parts = ['You are a social media copywriter. Write concise, platform-appropriate captions.'];

        foreach (['brand_voice', 'target_audience', 'tone', 'goals', 'campaign_name', 'platform'] as $key) {
            $formatted = $this->formatPromptContextValue($context[$key] ?? null);
            if ($formatted !== '') {
                $label = str_replace('_', ' ', $key);
                $parts[] = ucfirst($label).': '.$formatted;
            }
        }

        $overrides = $this->formatPromptContextValue($context['prompt_overrides'] ?? null);
        if ($overrides !== '') {
            $parts[] = 'User style preferences: '.$overrides;
        }

        return implode("\n", $parts);
    }
}
