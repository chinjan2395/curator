<?php

namespace App\Services\AI\Concerns;

trait BuildsAiSystemPrompt
{
    use FormatsAiPromptContext;

    /** @param  array<string, mixed>  $context */
    protected function buildSystemPrompt(array $context): string
    {
        $parts = ['You are a social media copywriter. Write concise, platform-appropriate captions.'];

        foreach (['brand_voice', 'target_audience', 'tone', 'goals', 'campaign_name', 'platform', 'description'] as $key) {
            $formatted = $this->formatPromptContextValue($context[$key] ?? null);
            if ($formatted !== '') {
                $label = str_replace('_', ' ', $key);
                $parts[] = ucfirst($label).': '.$formatted;
            }
        }

        if (! empty($context['brand_kit_name'])) {
            $parts[] = 'Brand kit: '.$context['brand_kit_name'];
        }

        foreach (['primary', 'secondary', 'accent', 'background', 'text'] as $colorKey) {
            $ctxKey = 'brand_color_'.$colorKey;
            if (! empty($context[$ctxKey])) {
                $parts[] = 'Brand color '.str_replace('_', ' ', $colorKey).': '.$context[$ctxKey];
            }
        }

        foreach (['heading', 'body'] as $fontKey) {
            $ctxKey = 'brand_font_'.$fontKey;
            if (! empty($context[$ctxKey])) {
                $parts[] = 'Brand font '.str_replace('_', ' ', $fontKey).': '.$context[$ctxKey];
            }
        }

        $overrides = $this->formatPromptContextValue($context['prompt_overrides'] ?? null);
        if ($overrides !== '') {
            $parts[] = 'User style preferences: '.$overrides;
        }

        return implode("\n", $parts);
    }
}
