<?php

namespace Tests\Unit;

use App\Services\AI\Image\ImageGenerationRequest;
use App\Services\AI\Image\Providers\FluxImageProvider;
use App\Services\AI\Image\ReferenceImage;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FluxImageProviderTest extends TestCase
{
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const POLLING_URL = 'https://api.bfl.ai/v1/get_result?id=abc';

    private const DELIVERY_URL = 'https://delivery.bfl.ai/results/abc.png';

    private function binary(): string
    {
        return (string) base64_decode(self::PIXEL, true);
    }

    private function reference(): ReferenceImage
    {
        return new ReferenceImage($this->binary(), 'image/png', 'reference.png');
    }

    public function test_it_creates_polls_and_downloads_the_finished_image(): void
    {
        Http::fake([
            'api.bfl.ai/v1/get_result*' => Http::sequence()
                ->push(['status' => 'Pending'])
                ->push(['status' => 'Ready', 'result' => ['sample' => self::DELIVERY_URL]]),
            'api.bfl.ai/v1/*' => Http::response(['id' => 'abc', 'polling_url' => self::POLLING_URL]),
            'delivery.bfl.ai/*' => Http::response($this->binary(), 200, ['Content-Type' => 'image/png']),
        ]);

        $image = (new FluxImageProvider('bfl-test'))->generateImage(
            new ImageGenerationRequest(
                prompt: 'A marble flat lay',
                references: [$this->reference()],
                size: '1024x1024',
                model: 'flux-kontext-pro',
            ),
        );

        $this->assertSame('image/png', $image->mimeType);
        $this->assertSame($this->binary(), $image->content);

        // Create call carries the base64 reference and the api key header.
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.bfl.ai/v1/flux-kontext-pro'
                && ($request->data()['input_image'] ?? null) === base64_encode($this->binary())
                && $request->hasHeader('x-key', 'bfl-test');
        });
    }

    public function test_pixel_sizes_are_converted_to_an_aspect_ratio(): void
    {
        Http::fake([
            'api.bfl.ai/v1/get_result*' => Http::response(['status' => 'Ready', 'result' => ['sample' => self::DELIVERY_URL]]),
            'api.bfl.ai/v1/*' => Http::response(['polling_url' => self::POLLING_URL]),
            'delivery.bfl.ai/*' => Http::response($this->binary(), 200, ['Content-Type' => 'image/png']),
        ]);

        (new FluxImageProvider('bfl-test'))->generateImage(
            new ImageGenerationRequest(prompt: 'Tall image', size: '1024x1536'),
        );

        Http::assertSent(function ($request) {
            return ! str_contains($request->url(), 'get_result')
                && ($request->data()['aspect_ratio'] ?? null) === '2:3';
        });
    }

    public function test_a_moderated_or_failed_job_raises_the_providers_reason(): void
    {
        Http::fake([
            'api.bfl.ai/v1/get_result*' => Http::response(['status' => 'Content Moderated']),
            'api.bfl.ai/v1/*' => Http::response(['polling_url' => self::POLLING_URL]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Content Moderated');

        (new FluxImageProvider('bfl-test'))->generateImage(new ImageGenerationRequest(prompt: 'Nope'));
    }

    public function test_a_delivery_url_on_an_unexpected_host_is_refused(): void
    {
        Http::fake([
            'api.bfl.ai/v1/get_result*' => Http::response([
                'status' => 'Ready',
                'result' => ['sample' => 'https://attacker.example.com/internal'],
            ]),
            'api.bfl.ai/v1/*' => Http::response(['polling_url' => self::POLLING_URL]),
            '*' => Http::response('should never be requested'),
        ]);

        try {
            (new FluxImageProvider('bfl-test'))->generateImage(new ImageGenerationRequest(prompt: 'SSRF'));
            $this->fail('Expected the download host to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('unexpected host', $e->getMessage());
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'attacker.example.com'));
    }

    public function test_a_plain_http_delivery_url_is_refused(): void
    {
        Http::fake([
            'api.bfl.ai/v1/get_result*' => Http::response([
                'status' => 'Ready',
                'result' => ['sample' => 'http://delivery.bfl.ai/results/abc.png'],
            ]),
            'api.bfl.ai/v1/*' => Http::response(['polling_url' => self::POLLING_URL]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported delivery URL');

        (new FluxImageProvider('bfl-test'))->generateImage(new ImageGenerationRequest(prompt: 'Downgrade'));
    }

    public function test_a_missing_polling_url_is_reported(): void
    {
        Http::fake(['api.bfl.ai/v1/*' => Http::response(['id' => 'abc'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no polling URL');

        (new FluxImageProvider('bfl-test'))->generateImage(new ImageGenerationRequest(prompt: 'Anything'));
    }

    public function test_it_surfaces_a_create_error(): void
    {
        Http::fake(['api.bfl.ai/v1/*' => Http::response(['detail' => 'Invalid API key'], 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key');

        (new FluxImageProvider('bfl-bad'))->generateImage(new ImageGenerationRequest(prompt: 'Anything'));
    }
}
