<?php

namespace App\Services\AI\Concerns;

use Illuminate\Support\Facades\Http;
use RuntimeException;

trait CallsLlmApi
{
    protected function postJson(string $url, array $payload, ?string $apiKey = null): string
    {
        $request = Http::timeout(60)->acceptJson();

        if ($apiKey !== null && $apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        $response = $request->post($url, $payload);

        if (! $response->ok()) {
            throw new RuntimeException('LLM request failed: '.$this->llmErrorMessage($response->json(), $response->body()));
        }

        $content = data_get($response->json(), 'choices.0.message.content')
            ?? data_get($response->json(), 'message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('LLM returned empty content.');
        }

        return trim($content);
    }

    /**
     * Providers disagree on their error shape: OpenAI-compatible APIs nest a
     * string under error.message, xAI returns a flat string under error. Fall
     * back to the raw body only when neither shape matches.
     */
    private function llmErrorMessage(mixed $json, string $body): string
    {
        $nested = data_get($json, 'error.message');
        if (is_string($nested) && $nested !== '') {
            return $nested;
        }

        $flat = data_get($json, 'error');
        if (is_string($flat) && $flat !== '') {
            return $flat;
        }

        return $body;
    }
}
