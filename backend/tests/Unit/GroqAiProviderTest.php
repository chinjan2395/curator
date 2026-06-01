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
}
