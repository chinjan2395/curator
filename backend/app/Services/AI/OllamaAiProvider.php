<?php

namespace App\Services\AI;

use App\Services\AI\Concerns\CallsLlmApi;
use App\Services\AI\Concerns\FormatsAiPromptContext;

class OllamaAiProvider implements AiProviderInterface
{
    use CallsLlmApi;
    use FormatsAiPromptContext;

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

    /** @param  array<string, mixed>  $context */
    private function buildSystemPrompt(array $context): string
    {
        $parts = ['You are a social media copywriter.'];

        foreach (['brand_voice', 'target_audience', 'tone', 'goals', 'campaign_name', 'platform'] as $key) {
            $formatted = $this->formatPromptContextValue($context[$key] ?? null);
            if ($formatted !== '') {
                $parts[] = str_replace('_', ' ', $key).': '.$formatted;
            }
        }

        if (!empty($context['brand_kit_name'])) {
            $parts[] = 'Brand kit: '.$context['brand_kit_name'];
        }
        if (!empty($context['brand_primary_color'])) {
            $parts[] = 'Brand primary color: '.$context['brand_primary_color'];
        }
        if (!empty($context['brand_font'])) {
            $parts[] = 'Brand font style: '.$context['brand_font'];
        }

        $overrides = $this->formatPromptContextValue($context['prompt_overrides'] ?? null);
        if ($overrides !== '') {
            $parts[] = 'User style preferences: '.$overrides;
        }

        return implode("\n", $parts);
    }
}
