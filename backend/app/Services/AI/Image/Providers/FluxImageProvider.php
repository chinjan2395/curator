<?php

namespace App\Services\AI\Image\Providers;

use App\Services\AI\Image\AiImageProviderInterface;
use App\Services\AI\Image\GeneratedImage;
use App\Services\AI\Image\ImageGenerationRequest;
use App\Support\AiImageProviders;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Black Forest Labs FLUX.
 *
 * https://docs.bfl.ai/
 *
 * BFL is asynchronous: the create call returns a polling URL, and the finished
 * image arrives as a short-lived signed delivery URL we then download.
 */
class FluxImageProvider implements AiImageProviderInterface
{
    private const BASE_URL = 'https://api.bfl.ai/v1/';

    /** Hosts the finished image may be downloaded from. */
    private const DELIVERY_HOST_SUFFIXES = ['bfl.ai', 'bfl.ml', 'blackforestlabs.ai'];

    private const MAX_POLL_ATTEMPTS = 60;

    private const POLL_INTERVAL_MICROSECONDS = 2_000_000;

    public function __construct(private readonly string $apiKey) {}

    public function name(): string
    {
        return AiImageProviders::FLUX;
    }

    public function supportsReference(): bool
    {
        return true;
    }

    public function generateImage(ImageGenerationRequest $request): GeneratedImage
    {
        $model = $request->model
            ?: (string) config('services.ai.image.flux.model', 'flux-kontext-pro');

        $payload = array_filter([
            'prompt' => $request->prompt,
            'aspect_ratio' => $this->aspectRatio($request->size),
            'output_format' => 'png',
            // FLUX conditions on a single base64 input image.
            'input_image' => $request->firstReference()?->base64(),
        ], static fn ($value) => $value !== null);

        $created = $this->client()->asJson()->post(self::BASE_URL.$model, $payload);

        if (! $created->successful()) {
            throw new RuntimeException('FLUX image generation failed: '.$this->errorMessage($created->json(), $created->body()));
        }

        $pollingUrl = $created->json('polling_url');
        if (! is_string($pollingUrl) || $pollingUrl === '') {
            throw new RuntimeException('FLUX image generation returned no polling URL.');
        }

        return $this->download($this->pollForResult($pollingUrl));
    }

    /** Poll until the job is Ready, returning the signed delivery URL. */
    private function pollForResult(string $pollingUrl): string
    {
        $this->assertSafeUrl($pollingUrl, 'polling');

        for ($attempt = 0; $attempt < self::MAX_POLL_ATTEMPTS; $attempt++) {
            $response = $this->client()->acceptJson()->get($pollingUrl);

            if (! $response->successful()) {
                throw new RuntimeException('FLUX polling failed: '.$this->errorMessage($response->json(), $response->body()));
            }

            $status = (string) $response->json('status');

            if ($status === 'Ready') {
                $sample = $response->json('result.sample');
                if (! is_string($sample) || $sample === '') {
                    throw new RuntimeException('FLUX finished but returned no image URL.');
                }

                return $sample;
            }

            if (in_array($status, ['Error', 'Failed', 'Content Moderated', 'Request Moderated'], true)) {
                $detail = $response->json('result.error') ?? $response->json('details');
                throw new RuntimeException('FLUX image generation failed: '.(is_string($detail) && $detail !== '' ? $detail : $status));
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        throw new RuntimeException('FLUX image generation timed out before the image was ready.');
    }

    private function download(string $url): GeneratedImage
    {
        $this->assertSafeUrl($url, 'delivery');

        $response = Http::timeout(120)
            // Do not chase a redirect off the allow-listed host.
            ->withoutRedirecting()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('FLUX image download failed with status '.$response->status().'.');
        }

        $content = $response->body();
        if ($content === '') {
            throw new RuntimeException('FLUX image download returned an empty body.');
        }

        $mime = $response->header('Content-Type') ?: 'image/png';

        return new GeneratedImage($content, explode(';', $mime)[0]);
    }

    /**
     * Only follow https URLs on BFL-controlled hosts, so a compromised or
     * unexpected response cannot make the server fetch an arbitrary address.
     */
    private function assertSafeUrl(string $url, string $kind): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            throw new RuntimeException("FLUX returned an unsupported {$kind} URL.");
        }

        foreach (self::DELIVERY_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return;
            }
        }

        throw new RuntimeException("FLUX returned a {$kind} URL on an unexpected host: {$host}");
    }

    /** FLUX takes an aspect ratio rather than pixel dimensions. */
    private function aspectRatio(?string $size): ?string
    {
        if ($size === null || ! str_contains($size, 'x')) {
            return null;
        }

        [$width, $height] = array_map('intval', explode('x', $size, 2));
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $divisor = $this->greatestCommonDivisor($width, $height);

        return ($width / $divisor).':'.($height / $divisor);
    }

    private function greatestCommonDivisor(int $a, int $b): int
    {
        return $b === 0 ? max($a, 1) : $this->greatestCommonDivisor($b, $a % $b);
    }

    private function client(): PendingRequest
    {
        return Http::timeout(120)->withHeaders(['x-key' => $this->apiKey]);
    }

    private function errorMessage(mixed $json, string $body): string
    {
        foreach (['detail', 'error', 'message'] as $key) {
            $message = data_get($json, $key);
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return $body;
    }
}
