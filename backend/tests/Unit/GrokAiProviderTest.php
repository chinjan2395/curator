<?php

namespace Tests\Unit;

use App\Services\AI\AiTextProviderFactory;
use App\Services\AI\GrokAiProvider;
use App\Services\AI\Text\ResolvedTextKey;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GrokAiProviderTest extends TestCase
{
    public function test_grok_provider_parses_chat_response(): void
    {
        config(['services.ai.grok.api_key' => 'test-key']);

        Http::fake([
            'api.x.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Hello from Grok']],
                ],
            ]),
        ]);

        $provider = new GrokAiProvider;
        $text = $provider->generateText('Write a caption', ['platform' => 'instagram']);

        $this->assertSame('Hello from Grok', $text);
    }

    public function test_grok_provider_formats_array_context_in_system_prompt(): void
    {
        config(['services.ai.grok.api_key' => 'test-key']);

        Http::fake([
            'api.x.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Caption']],
                ],
            ]),
        ]);

        $provider = new GrokAiProvider;
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

    public function test_grok_provider_calls_the_x_ai_chat_completions_endpoint_with_the_model(): void
    {
        config(['services.ai.grok.api_key' => 'test-key']);

        Http::fake([
            'api.x.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'Caption']]],
            ]),
        ]);

        (new GrokAiProvider(model: 'grok-4-0709'))->generateText('Write a caption');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.x.ai/v1/chat/completions'
                && $request->data()['model'] === 'grok-4-0709'
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    public function test_it_throws_when_no_api_key_is_configured(): void
    {
        config(['services.ai.grok.api_key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XAI_API_KEY is not configured.');

        (new GrokAiProvider)->generateText('Write a caption');
    }

    public function test_it_surfaces_a_failed_request(): void
    {
        config(['services.ai.grok.api_key' => 'test-key']);

        Http::fake([
            'api.x.ai/*' => Http::response('Unauthorized', 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LLM request failed');

        (new GrokAiProvider)->generateText('Write a caption');
    }

    public function test_it_surfaces_x_ais_flat_error_shape(): void
    {
        config(['services.ai.grok.api_key' => 'test-key']);

        Http::fake([
            'api.x.ai/*' => Http::response([
                'code' => 'permission-denied',
                'error' => 'Your newly created team doesn\'t have any credits or licenses yet.',
            ], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Your newly created team doesn't have any credits or licenses yet.");

        (new GrokAiProvider)->generateText('Write a caption');
    }

    public function test_name_returns_grok(): void
    {
        $this->assertSame('grok', (new GrokAiProvider)->name());
    }

    public function test_the_factory_produces_a_grok_provider(): void
    {
        $provider = (new AiTextProviderFactory)->make(
            ResolvedTextKey::byok('grok', 'test-key'),
            'grok-4-0709',
        );

        $this->assertInstanceOf(GrokAiProvider::class, $provider);
        $this->assertSame('grok', $provider->name());
    }
}
