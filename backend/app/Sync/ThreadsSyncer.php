<?php

namespace App\Sync;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ThreadsSyncer
{
    private const THREADS_API_BASE = 'https://graph.threads.net/v1.0';
    private const THREAD_FIELDS = 'id,media_type,media_url,permalink,text,timestamp,thumbnail_url,username';

    public function account(SocialCredential $credential): array|JsonResponse
    {
        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'Threads credential has expired or could not be refreshed. Reconnect Threads in Credentials.'], 422);
        }

        $resolved = $this->resolveMe($token);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        return ['accounts' => [['id' => $resolved['id'], 'username' => $resolved['username'], 'name' => $resolved['name']]]];
    }

    public function test(SocialCredential $credential): array|JsonResponse
    {
        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'Threads credential has expired or could not be refreshed. Reconnect Threads in Credentials.'], 422);
        }

        $resolved = $this->resolveMe($token);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $resp = Http::withToken($token)->timeout(20)->get(self::THREADS_API_BASE.'/me/threads', [
            'fields' => self::THREAD_FIELDS,
            'limit' => 5,
        ]);

        if (! $resp->ok()) {
            return response()->json(['message' => $this->errorMessage($resp, 'Failed to load posts from Threads.'), 'error' => $resp->json()], min(499, max(400, $resp->status())));
        }

        return [
            'message' => 'Threads connection successful.',
            'user_id' => $resolved['id'],
            'username' => $resolved['username'] ?? null,
            'sample_count' => count($resp->json('data', [])),
        ];
    }

    public function sync(Feed $feed): JsonResponse
    {
        $credential = $feed->socialCredential;

        if (! $credential || $credential->provider !== 'threads') {
            return response()->json(['message' => 'No Threads credential attached to this feed. Connect Threads and assign the credential to the feed.'], 422);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'Threads credential has expired or could not be refreshed. Reconnect Threads in Credentials.'], 422);
        }

        $meResolved = $this->resolveMe($token);
        if ($meResolved instanceof JsonResponse) {
            return $meResolved;
        }

        $threadsUser = trim((string) ($meResolved['username'] ?? ''));
        if ($threadsUser !== '') {
            $feed->source_account_label = '@'.ltrim($threadsUser, '@');
        }
        $pic = trim((string) ($meResolved['threads_profile_picture_url'] ?? ''));
        if ($pic !== '') {
            $feed->account_avatar_url = $pic;
        }
        if ($feed->isDirty()) {
            $feed->save();
        }

        $resp = Http::withToken($token)->timeout(20)->get(self::THREADS_API_BASE.'/me/threads', [
            'fields' => self::THREAD_FIELDS,
            'limit' => 25,
        ]);

        if (! $resp->ok()) {
            return response()->json(['message' => $this->errorMessage($resp, 'Failed to load posts from Threads.'), 'error' => $resp->json()], min(499, max(400, $resp->status())));
        }

        $created = 0;
        foreach ($resp->json('data', []) as $item) {
            $externalId = $item['id'] ?? null;
            if (! $externalId) {
                continue;
            }

            $body = trim((string) ($item['text'] ?? ''));
            $title = $body !== '' ? mb_substr($body, 0, 120) : 'Threads post';
            $thumb = $this->thumbnail($item);
            $permalink = $item['permalink'] ?? null;
            $mediaUrl = $item['media_url'] ?? null;
            $link = is_string($permalink) && $permalink !== '' ? $permalink : (is_string($mediaUrl) ? $mediaUrl : null);

            Post::updateOrCreate(
                ['feed_id' => $feed->id, 'external_id' => (string) $externalId],
                [
                    'title' => $title,
                    'content' => $body,
                    'thumbnail_url' => $thumb,
                    'video_url' => $link,
                    'posted_at' => $item['timestamp'] ?? null,
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json(['message' => 'Threads sync complete', 'created' => $created, 'last_synced_at' => $feed->last_synced_at]);
    }

    private function resolveMe(string $token): array|JsonResponse
    {
        $resp = Http::withToken($token)->get(self::THREADS_API_BASE.'/me', [
            'fields' => 'id,username,name,threads_profile_picture_url',
        ]);

        if (! $resp->ok()) {
            return response()->json(['message' => $this->errorMessage($resp, 'Could not load your Threads account.'), 'error' => $resp->json()], min(499, max(400, $resp->status())));
        }

        $id = $resp->json('id');
        if (! $id) {
            return response()->json(['message' => 'Unexpected Threads API response for /me.', 'error' => $resp->json()], 422);
        }

        return [
            'id' => (string) $id,
            'username' => (string) ($resp->json('username') ?? ''),
            'name' => (string) ($resp->json('name') ?? ''),
            'threads_profile_picture_url' => (string) ($resp->json('threads_profile_picture_url') ?? ''),
        ];
    }

    private function thumbnail(array $item): ?string
    {
        if (! empty($item['thumbnail_url'])) {
            return (string) $item['thumbnail_url'];
        }
        if (($item['media_type'] ?? '') === 'IMAGE' && ! empty($item['media_url'])) {
            return (string) $item['media_url'];
        }

        return null;
    }

    private function errorMessage($response, string $fallback): string
    {
        $err = $response->json('error');
        if (is_array($err) && ! empty($err['message'])) {
            return (string) $err['message'];
        }

        return $fallback;
    }
}
