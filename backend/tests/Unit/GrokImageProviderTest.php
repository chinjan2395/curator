<?php

namespace Tests\Unit;

use App\Services\AI\Image\AiImageProviderFactory;
use App\Services\AI\Image\ImageGenerationRequest;
use App\Services\AI\Image\Providers\GrokImageProvider;
use App\Services\AI\Image\ReferenceImage;
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

    private function reference(): ReferenceImage
    {
        return new ReferenceImage((string) base64_decode(self::PIXEL, true), 'image/png', 'reference.png');
    }

    public function test_name_returns_grok(): void
    {
        $this->assertSame('grok', (new GrokImageProvider('xai-test'))->name());
    }

    public function test_it_supports_a_reference_image(): void
    {
        $this->assertTrue((new GrokImageProvider('xai-test'))->supportsReference());
    }

    public function test_it_calls_the_generations_endpoint_and_returns_a_jpeg(): void
    {
        $this->fakeSuccess();

        $image = (new GrokImageProvider('xai-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'A marble flat lay', model: 'grok-imagine-image-2.0'),
        );

        $this->assertSame('image/jpeg', $image->mimeType);
        $this->assertNotEmpty($image->content);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.x.ai/v1/images/generations'
                && $request->data()['prompt'] === 'A marble flat lay'
                && $request->data()['model'] === 'grok-imagine-image-2.0'
                && $request->data()['response_format'] === 'b64_json'
                && $request->hasHeader('Authorization', 'Bearer xai-test');
        });
    }

    public function test_it_falls_back_to_the_configured_default_model(): void
    {
        $this->fakeSuccess();
        config(['services.ai.image.grok.model' => 'grok-imagine-image-2.0']);

        (new GrokImageProvider('xai-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'No explicit model'),
        );

        Http::assertSent(fn ($request) => $request->data()['model'] === 'grok-imagine-image-2.0');
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

    public function test_it_surfaces_x_ais_flat_error_shape(): void
    {
        Http::fake([
            'api.x.ai/*' => Http::response([
                'code' => 'permission-denied',
                'error' => 'Your newly created team doesn\'t have any credits or licenses yet.',
            ], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Your newly created team doesn't have any credits or licenses yet.");

        (new GrokImageProvider('xai-test'))->generateImage(
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

    public function test_a_single_reference_calls_the_edits_endpoint_with_an_inline_data_uri(): void
    {
        $this->fakeSuccess();

        $image = (new GrokImageProvider('xai-test'))->generateImage(
            new ImageGenerationRequest(
                prompt: 'Put the product on marble',
                references: [$this->reference()],
                model: 'grok-imagine-image-2.0',
            ),
        );

        $this->assertSame('image/jpeg', $image->mimeType);
        $this->assertNotEmpty($image->content);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://api.x.ai/v1/images/edits'
                && $data['prompt'] === 'Put the product on marble'
                && $data['model'] === 'grok-imagine-image-2.0'
                && $data['image']['type'] === 'image_url'
                && str_starts_with($data['image']['url'], 'data:image/png;base64,')
                && ! isset($data['images']);
        });
    }

    public function test_multiple_references_are_sent_as_an_images_array(): void
    {
        $this->fakeSuccess();

        (new GrokImageProvider('xai-test'))->generateImage(
            new ImageGenerationRequest(
                prompt: 'Combine these products',
                references: [$this->reference(), $this->reference()],
                model: 'grok-imagine-image-2.0',
            ),
        );

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://api.x.ai/v1/images/edits'
                && ! isset($data['image'])
                && count($data['images']) === 2
                && $data['images'][0]['type'] === 'image_url'
                && str_starts_with($data['images'][0]['url'], 'data:image/png;base64,');
        });
    }
}
