<?php

namespace App\Services\Social\Support;

use App\Models\SocialCredential;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ThreadsPublishingClient
{
    public const API_BASE = 'https://graph.threads.net/v1.0';

    private const STATUS_FINISHED = 'FINISHED';

    private const STATUS_ERROR = 'ERROR';

    public function resolveUserId(SocialCredential $credential, string $accessToken): string
    {
        $accountId = trim((string) ($credential->account_id ?? ''));
        if ($accountId !== '') {
            return $accountId;
        }

        $response = Http::withToken($accessToken)->get(self::API_BASE.'/me', [
            'fields' => 'id',
        ]);

        $id = $this->okResponse($response)->json('id');
        if (! is_string($id) && ! is_numeric($id)) {
            throw new RuntimeException('Could not resolve Threads user id from /me.');
        }

        return (string) $id;
    }

    /**
     * @param  array<string, string>  $params
     * @return array{creation_id: string}
     */
    public function createContainer(string $userId, string $accessToken, array $params): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->asForm()
            ->post(self::API_BASE.'/'.$userId.'/threads', $params);

        $creationId = (string) ($this->okResponse($response)->json('id') ?? '');
        if ($creationId === '') {
            throw new RuntimeException('Threads API returned no container id.');
        }

        return ['creation_id' => $creationId];
    }

    public function waitUntilReady(
        string $accessToken,
        string $containerId,
        int $maxAttempts = 5,
        int $sleepSeconds = 60,
    ): void {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $response = Http::withToken($accessToken)->get(self::API_BASE.'/'.$containerId, [
                'fields' => 'status,error_message',
            ]);

            $body = $this->okResponse($response);
            $status = (string) ($body->json('status') ?? '');

            if ($status === self::STATUS_FINISHED) {
                return;
            }

            if ($status === self::STATUS_ERROR) {
                $message = (string) ($body->json('error_message') ?? 'unknown');
                throw new RuntimeException('Threads media container failed: '.$message);
            }

            if ($attempt < $maxAttempts - 1 && $sleepSeconds > 0 && ! app()->environment('testing')) {
                sleep($sleepSeconds);
            }
        }

        throw new RuntimeException('Threads media container timed out before becoming ready.');
    }

    /**
     * @return array{platform_post_id: string}
     */
    public function publishContainer(string $userId, string $accessToken, string $creationId): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->asForm()
            ->post(self::API_BASE.'/'.$userId.'/threads_publish', [
                'creation_id' => $creationId,
            ]);

        $postId = (string) ($this->okResponse($response)->json('id') ?? '');
        if ($postId === '') {
            throw new RuntimeException('Threads publish returned no post id.');
        }

        return ['platform_post_id' => $postId];
    }

    public function fetchPermalink(string $accessToken, string $mediaId): ?string
    {
        $response = Http::withToken($accessToken)->get(self::API_BASE.'/'.$mediaId, [
            'fields' => 'permalink',
        ]);

        if (! $response->ok()) {
            return null;
        }

        $permalink = $response->json('permalink');

        return is_string($permalink) && $permalink !== '' ? $permalink : null;
    }

    private function okResponse(Response $response): Response
    {
        if (! $response->ok()) {
            $err = $response->json('error');
            $message = is_array($err) ? (string) ($err['message'] ?? $response->body()) : $response->body();
            throw new RuntimeException('Threads API error: '.$message);
        }

        return $response;
    }
}
