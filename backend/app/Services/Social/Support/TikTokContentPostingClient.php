<?php

namespace App\Services\Social\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TikTokContentPostingClient
{
    private const API_BASE = 'https://open.tiktokapis.com/v2';

    private const STATUS_COMPLETE = 'PUBLISH_COMPLETE';

    private const STATUS_FAILED = 'FAILED';

    /** @return array<string, mixed> */
    public function queryCreatorInfo(string $accessToken): array
    {
        return $this->post('/post/publish/creator_info/query/', $accessToken, []);
    }

    /**
     * @param  array<string, mixed>  $postInfo
     * @return array{publish_id: string}
     */
    public function initVideoPullFromUrl(string $accessToken, string $videoUrl, array $postInfo): array
    {
        $data = $this->post('/post/publish/video/init/', $accessToken, [
            'post_info' => $postInfo,
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'video_url' => $videoUrl,
            ],
        ]);

        $publishId = (string) ($data['publish_id'] ?? '');
        if ($publishId === '') {
            throw new RuntimeException('TikTok publish init returned no publish_id.');
        }

        return ['publish_id' => $publishId];
    }

    /** @return array<string, mixed> */
    public function fetchStatus(string $accessToken, string $publishId): array
    {
        return $this->post('/post/publish/status/fetch/', $accessToken, [
            'publish_id' => $publishId,
        ]);
    }

    /**
     * Poll until publish completes or fails.
     *
     * @return array<string, mixed>
     */
    public function waitUntilComplete(
        string $accessToken,
        string $publishId,
        int $maxAttempts = 30,
        int $sleepSeconds = 2,
    ): array {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $status = $this->fetchStatus($accessToken, $publishId);
            $state = (string) ($status['status'] ?? '');

            if ($state === self::STATUS_COMPLETE) {
                return $status;
            }

            if ($state === self::STATUS_FAILED) {
                $reason = (string) ($status['fail_reason'] ?? 'unknown');
                throw new RuntimeException('TikTok publish failed: '.$reason);
            }

            if ($attempt < $maxAttempts - 1 && $sleepSeconds > 0 && ! app()->environment('testing')) {
                sleep($sleepSeconds);
            }
        }

        throw new RuntimeException('TikTok publish timed out waiting for completion.');
    }

    /** @return array<string, mixed> */
    private function post(string $path, string $accessToken, array $body): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(60)
            ->withHeaders(['Content-Type' => 'application/json; charset=UTF-8'])
            ->post(self::API_BASE.$path, $body);

        return $this->parseData($response);
    }

    /** @return array<string, mixed> */
    private function parseData(Response $response): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('TikTok API returned an invalid response.');
        }

        $errorCode = (string) ($json['error']['code'] ?? '');
        if (! $response->ok() || ($errorCode !== '' && $errorCode !== 'ok')) {
            $message = (string) ($json['error']['message'] ?? $response->body());
            $suffix = $errorCode !== '' ? " ({$errorCode})" : '';

            throw new RuntimeException('TikTok API error: '.$message.$suffix);
        }

        $data = $json['data'] ?? [];

        return is_array($data) ? $data : [];
    }
}
