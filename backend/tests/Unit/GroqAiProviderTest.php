<?php

namespace Tests\Unit;

use App\Services\AI\GroqAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqAiProviderTest extends TestCase
{
    public function test_groq_provider_parses_chat_response(): void
    {
        config(['services.ai.groq.api_key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Hello from Groq']],
                ],
            ]),
        ]);

        $provider = new GroqAiProvider;
        $text = $provider->generateText('Write a caption', ['platform' => 'instagram']);

        $this->assertSame('Hello from Groq', $text);
    }

    public function test_groq_provider_formats_array_context_in_system_prompt(): void
    {
        config(['services.ai.groq.api_key' => 'test-key']);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Caption']],
                ],
            ]),
        ]);

        $provider = new GroqAiProvider;
        $provider->generateText('Write a caption', [
            'target_audience' => ['marketing managers', 'agency owners'],
            'goals' => ['trial signups', 'awareness'],
            'platform' => 'instagram',
        ]);

        Http::assertSent(function ($request) {
            $system = data_get($request->data(), 'messages.0.content', '');

            return str_contains($system, 'Target audience: marketing managers, agency owners')
                && str_contains($system, 'Goals: trial signups, awareness');
        });
    }
}
