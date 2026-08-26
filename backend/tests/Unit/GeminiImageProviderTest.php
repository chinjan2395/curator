<?php

namespace Tests\Unit;

use App\Services\AI\Image\ImageGenerationRequest;
use App\Services\AI\Image\Providers\GeminiImageProvider;
use App\Services\AI\Image\ReferenceImage;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiImageProviderTest extends TestCase
{
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function reference(): ReferenceImage
    {
        return new ReferenceImage((string) base64_decode(self::PIXEL, true), 'image/png', 'reference.png');
    }

    public function test_the_reference_travels_as_an_inline_data_part(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['inline_data' => ['mime_type' => 'image/png', 'data' => self::PIXEL]]]],
                ]],
            ]),
        ]);

        $image = (new GeminiImageProvider('gemini-test'))->generateImage(
            new ImageGenerationRequest(
                prompt: 'Put the product on marble',
                references: [$this->reference()],
                size: '1024x1024',
                model: 'gemini-2.5-flash-image',
            ),
        );

        $this->assertSame('image/png', $image->mimeType);
        $this->assertSame((string) base64_decode(self::PIXEL, true), $image->content);

        Http::assertSent(function ($request) {
            $parts = data_get($request->data(), 'contents.0.parts');

            return str_contains($request->url(), 'gemini-2.5-flash-image:generateContent')
                && $request->hasHeader('x-goog-api-key', 'gemini-test')
                && $parts[0]['text'] === 'Put the product on marble'
                && $parts[1]['inline_data']['data'] === self::PIXEL
                && $parts[1]['inline_data']['mime_type'] === 'image/png';
        });
    }

    public function test_it_parses_the_camel_case_response_spelling(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['text' => 'Here is your image'],
                        ['inlineData' => ['mimeType' => 'image/webp', 'data' => self::PIXEL]],
                    ]],
                ]],
            ]),
        ]);

        $image = (new GeminiImageProvider('gemini-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'Anything'),
        );

        $this->assertSame('image/webp', $image->mimeType);
    }

    public function test_pixel_sizes_are_converted_to_an_aspect_ratio(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['inline_data' => ['data' => self::PIXEL]]]]]],
            ]),
        ]);

        (new GeminiImageProvider('gemini-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'Wide image', size: '1536x1024'),
        );

        Http::assertSent(
            fn ($request) => data_get($request->data(), 'generationConfig.imageConfig.aspectRatio') === '3:2',
        );
    }

    public function test_a_refusal_without_an_image_is_reported_with_its_reason(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['finishReason' => 'SAFETY', 'content' => ['parts' => []]]],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SAFETY');

        (new GeminiImageProvider('gemini-test'))->generateImage(new ImageGenerationRequest(prompt: 'Nope'));
    }

    public function test_it_surfaces_the_providers_error_message(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                ['error' => ['message' => 'API key not valid.']],
                400,
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API key not valid.');

        (new GeminiImageProvider('gemini-bad'))->generateImage(new ImageGenerationRequest(prompt: 'Anything'));
    }
}
