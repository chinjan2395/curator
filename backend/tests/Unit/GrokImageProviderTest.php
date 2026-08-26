<?php

namespace Tests\Unit;

use App\Services\AI\Image\AiImageProviderFactory;
use App\Services\AI\Image\ImageGenerationRequest;
use App\Services\AI\Image\Providers\GrokImageProvider;
use App\Services\AI\Image\ResolvedImageKey;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GrokImageProviderTest extends TestCase
{
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function fakeSuccess(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response(['data' => [['b64_json' => self::PIXEL]]]),
        ]);
    }

    public function test_name_returns_grok(): void
    {
        $this->assertSame('grok', (new GrokImageProvider('xai-test'))->name());
    }

    public function test_it_does_not_support_a_reference_image(): void
    {
        $this->assertFalse((new GrokImageProvider('xai-test'))->supportsReference());
    }

    public function test_it_calls_the_generations_endpoint_and_returns_a_jpeg(): void
    {
        $this->fakeSuccess();

        $image = (new GrokImageProvider('xai-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'A marble flat lay', model: 'grok-2-image-1212'),
        );

        $this->assertSame('image/jpeg', $image->mimeType);
        $this->assertNotEmpty($image->content);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.x.ai/v1/images/generations'
                && $request->data()['prompt'] === 'A marble flat lay'
                && $request->data()['model'] === 'grok-2-image-1212'
                && $request->data()['response_format'] === 'b64_json'
                && $request->hasHeader('Authorization', 'Bearer xai-test');
        });
    }

    public function test_it_falls_back_to_the_configured_default_model(): void
    {
        $this->fakeSuccess();
        config(['services.ai.image.grok.model' => 'grok-2-image-1212']);

        (new GrokImageProvider('xai-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'No explicit model'),
        );

        Http::assertSent(fn ($request) => $request->data()['model'] === 'grok-2-image-1212');
    }

    public function test_it_surfaces_the_providers_error_message(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response(['error' => ['message' => 'Invalid API key provided.']], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key provided.');

        (new GrokImageProvider('xai-bad'))->generateImage(
            new ImageGenerationRequest(prompt: 'Anything'),
        );
    }

    public function test_it_rejects_a_response_with_no_image_data(): void
    {
        Http::fake(['api.x.ai/*' => Http::response(['data' => [[]]])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('returned no image data');

        (new GrokImageProvider('xai-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'Anything'),
        );
    }

    public function test_the_factory_produces_a_grok_provider(): void
    {
        $provider = (new AiImageProviderFactory)->make(
            ResolvedImageKey::byok('grok', 'xai-test'),
        );

        $this->assertInstanceOf(GrokImageProvider::class, $provider);
        $this->assertSame('grok', $provider->name());
    }
}
