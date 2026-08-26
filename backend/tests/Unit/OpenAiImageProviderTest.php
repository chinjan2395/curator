<?php

namespace Tests\Unit;

use App\Services\AI\Image\ImageGenerationRequest;
use App\Services\AI\Image\Providers\OpenAiImageProvider;
use App\Services\AI\Image\ReferenceImage;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiImageProviderTest extends TestCase
{
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function fakeSuccess(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => self::PIXEL]]]),
        ]);
    }

    private function reference(): ReferenceImage
    {
        return new ReferenceImage(
            content: (string) base64_decode(self::PIXEL, true),
            mimeType: 'image/png',
            fileName: 'reference.png',
        );
    }

    public function test_without_a_reference_it_calls_the_generations_endpoint(): void
    {
        $this->fakeSuccess();

        $image = (new OpenAiImageProvider('sk-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'A marble flat lay', model: 'gpt-image-1', size: '1024x1024'),
        );

        $this->assertSame('image/png', $image->mimeType);
        $this->assertNotEmpty($image->content);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/images/generations'
                && $request->data()['prompt'] === 'A marble flat lay'
                && $request->data()['model'] === 'gpt-image-1'
                && $request->data()['size'] === '1024x1024';
        });
    }

    public function test_with_a_reference_it_calls_the_edits_endpoint_and_attaches_the_image(): void
    {
        $this->fakeSuccess();

        (new OpenAiImageProvider('sk-test'))->generateImage(
            new ImageGenerationRequest(
                prompt: 'Put the product on marble',
                references: [$this->reference()],
                model: 'gpt-image-1',
            ),
        );

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.openai.com/v1/images/edits') {
                return false;
            }

            $this->assertTrue($request->isMultipart());

            $names = array_column($request->data(), 'name');

            return in_array('image[]', $names, true)
                && in_array('prompt', $names, true);
        });
    }

    public function test_response_format_is_sent_only_for_dall_e_models(): void
    {
        $this->fakeSuccess();

        (new OpenAiImageProvider('sk-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'Legacy model', model: 'dall-e-3'),
        );

        Http::assertSent(fn ($request) => ($request->data()['response_format'] ?? null) === 'b64_json');

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => self::PIXEL]]]),
        ]);

        (new OpenAiImageProvider('sk-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'Current model', model: 'gpt-image-1'),
        );

        // gpt-image-* always returns b64_json and rejects the parameter outright.
        Http::assertSent(fn ($request) => ! array_key_exists('response_format', $request->data()));
    }

    public function test_it_surfaces_the_providers_error_message(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid API key provided.']], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key provided.');

        (new OpenAiImageProvider('sk-bad'))->generateImage(
            new ImageGenerationRequest(prompt: 'Anything'),
        );
    }

    public function test_it_rejects_a_response_with_no_image_data(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => [[]]])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('returned no image data');

        (new OpenAiImageProvider('sk-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'Anything'),
        );
    }
}
