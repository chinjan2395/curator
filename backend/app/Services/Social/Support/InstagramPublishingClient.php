<?php

namespace App\Services\Social\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramPublishingClient
{
    private const GRAPH_VERSION = 'v23.0';

    private const STATUS_FINISHED = 'FINISHED';

    private const STATUS_ERROR = 'ERROR';

    /**
     * @param  array<string, string>  $params
     * @return array{creation_id: string}
     */
    public function createContainer(string $igUserId, string $pageToken, array $params): array
    {
        $response = Http::asForm()->post(
            $this->graphUrl('/'.$igUserId.'/media'),
            array_merge($params, ['access_token' => $pageToken]),
        );

        $creationId = (string) ($this->okResponse($response)->json('id') ?? '');
        if ($creationId === '') {
            throw new RuntimeException('Instagram API returned no container id.');
        }

        return ['creation_id' => $creationId];
    }

    public function waitUntilReady(
        string $pageToken,
        string $containerId,
        int $maxAttempts = 30,
        int $sleepSeconds = 5,
    ): void {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $response = Http::get($this->graphUrl('/'.$containerId), [
                'fields' => 'status_code',
                'access_token' => $pageToken,
            ]);

            $body = $this->okResponse($response);
            $status = (string) ($body->json('status_code') ?? '');

            if ($status === self::STATUS_FINISHED) {
                return;
            }

            if ($status === self::STATUS_ERROR) {
                throw new RuntimeException('Instagram media container failed processing.');
            }

            if ($attempt < $maxAttempts - 1 && $sleepSeconds > 0 && ! app()->environment('testing')) {
                sleep($sleepSeconds);
            }
        }

        throw new RuntimeException('Instagram media container timed out before becoming ready.');
    }

    /**
     * @return array{platform_post_id: string}
     */
    public function publishContainer(string $igUserId, string $pageToken, string $creationId): array
    {
        $response = Http::asForm()->post(
            $this->graphUrl('/'.$igUserId.'/media_publish'),
            [
                'creation_id' => $creationId,
                'access_token' => $pageToken,
            ],
        );

        $postId = (string) ($this->okResponse($response)->json('id') ?? '');
        if ($postId === '') {
            throw new RuntimeException('Instagram publish returned no media id.');
        }

        return ['platform_post_id' => $postId];
    }

    public function fetchPermalink(string $mediaId, string $pageToken): ?string
    {
        $response = Http::get($this->graphUrl('/'.$mediaId), [
            'fields' => 'permalink',
            'access_token' => $pageToken,
        ]);

        if (! $response->ok()) {
            return null;
        }

        $link = $response->json('permalink');

        return is_string($link) && $link !== '' ? $link : null;
    }

    private function graphUrl(string $path): string
    {
        return 'https://graph.facebook.com/'.self::GRAPH_VERSION.$path;
    }

    private function okResponse(Response $response): Response
    {
        if (! $response->ok()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('Instagram API error: '.$message);
        }

        return $response;
    }
}
