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
            throw new RuntimeException('LLM request failed: '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content')
            ?? data_get($response->json(), 'message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('LLM returned empty content.');
        }

        return trim($content);
    }
}
