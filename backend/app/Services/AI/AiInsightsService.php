<?php

namespace App\Services\AI;

class AiInsightsService
{
    public function __construct(
        private readonly AiProviderInterface $provider,
    ) {}

    /** @param  array<string, mixed>  $overview */
    public function generateInsights(array $overview): array
    {
        if ($this->provider->name() === 'stub') {
            return [
                'Connect more platforms to unlock richer analytics insights.',
                'Approve more posts in Curator to grow your published feed.',
                'Schedule campaigns during peak engagement windows for your audience.',
            ];
        }

        try {
            $json = json_encode($overview, JSON_THROW_ON_ERROR);
            $text = $this->provider->generateText(
                "Given this analytics overview JSON, return exactly 5 short actionable insights as a numbered list:\n{$json}",
                ['tone' => 'analytical'],
            );

            $lines = preg_split('/\n+/', trim($text)) ?: [];

            return array_values(array_filter(array_map(static function ($line) {
                return trim(preg_replace('/^\d+[\).\s]+/', '', $line) ?? '');
            }, $lines)));
        } catch (\Throwable) {
            return ['Unable to generate AI insights right now. Try again later.'];
        }
    }
}
