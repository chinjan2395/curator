<?php

namespace App\Sync;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TikTokSyncer
{
    private const TIKTOK_API_BASE = 'https://open.tiktokapis.com/v2';
    private const VIDEO_FIELDS = 'id,title,video_description,create_time,cover_image_url,share_url';

    public function account(SocialCredential $credential): array|JsonResponse
    {
        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'TikTok credential has expired or could not be refreshed. Reconnect TikTok in Credentials.'], 422);
        }

        $user = $this->resolveMe($token);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return ['accounts' => [['open_id' => $user['open_id'], 'username' => $user['username'], 'display_name' => $user['display_name'], 'avatar_url' => $user['avatar_url']]]];
    }

    public function test(SocialCredential $credential): array|JsonResponse
    {
        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'TikTok credential has expired or could not be refreshed. Reconnect TikTok in Credentials.'], 422);
        }

        $user = $this->resolveMe($token);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $resp = Http::withToken($token)->timeout(20)->acceptJson()->post(self::TIKTOK_API_BASE.'/video/list/', [
            'max_count' => 5,
            'fields' => self::VIDEO_FIELDS,
        ]);

        if (! $resp->ok()) {
            return response()->json(['message' => $this->errorMessage($resp, 'Failed to load videos from TikTok.'), 'error' => $resp->json()], min(499, max(400, $resp->status())));
        }

        $videos = $resp->json('data.videos', []);

        return [
            'message' => 'TikTok connection successful.',
            'open_id' => $user['open_id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'sample_count' => count(is_array($videos) ? $videos : []),
        ];
    }

    public function sync(Feed $feed): JsonResponse
    {
        $credential = $feed->socialCredential;

        if (! $credential || $credential->provider !== 'tiktok') {
            return response()->json(['message' => 'No TikTok credential attached to this feed. Connect TikTok and assign the credential to the feed.'], 422);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'TikTok credential has expired or could not be refreshed. Reconnect TikTok in Credentials.'], 422);
        }

        $user = $this->resolveMe($token);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $resp = Http::withToken($token)->timeout(20)->acceptJson()->post(self::TIKTOK_API_BASE.'/video/list/', [
            'max_count' => 20,
            'fields' => self::VIDEO_FIELDS,
        ]);

        if (! $resp->ok()) {
            return response()->json(['message' => $this->errorMessage($resp, 'Failed to load videos from TikTok.'), 'error' => $resp->json()], min(499, max(400, $resp->status())));
        }

        $videos = $resp->json('data.videos', []);
        if (! is_array($videos)) {
            $videos = [];
        }

        $created = 0;
        foreach ($videos as $video) {
            $externalId = (string) ($video['id'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $title = trim((string) ($video['title'] ?? ''));
            $description = trim((string) ($video['video_description'] ?? ''));
            $body = trim($title."\n\n".$description) ?: 'TikTok video';
            $resolvedTitle = $title !== '' ? $title : (mb_substr($description, 0, 120) ?: 'TikTok video');

            Post::updateOrCreate(
                ['feed_id' => $feed->id, 'external_id' => $externalId],
                [
                    'title' => $resolvedTitle,
                    'content' => $body,
                    'thumbnail_url' => $video['cover_image_url'] ?? null,
                    'video_url' => $video['share_url'] ?? null,
                    'posted_at' => $this->normalizePostedAt($video['create_time'] ?? null),
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json(['message' => 'TikTok sync complete', 'created' => $created, 'last_synced_at' => $feed->last_synced_at, 'username' => $user['username'] ?? null]);
    }

    private function resolveMe(string $token): array|JsonResponse
    {
        $resp = Http::withToken($token)->acceptJson()->get(self::TIKTOK_API_BASE.'/user/info/', [
            'fields' => 'open_id,union_id,display_name,username,avatar_url',
        ]);

        if (! $resp->ok()) {
            return response()->json(['message' => $this->errorMessage($resp, 'Could not load your TikTok account.'), 'error' => $resp->json()], min(499, max(400, $resp->status())));
        }

        $user = $resp->json('data.user', []);
        $openId = (string) ($user['open_id'] ?? '');
        if ($openId === '') {
            return response()->json(['message' => 'Unexpected TikTok API response for user info.', 'error' => $resp->json()], 422);
        }

        return [
            'open_id' => $openId,
            'username' => (string) ($user['username'] ?? ''),
            'display_name' => (string) ($user['display_name'] ?? ''),
            'avatar_url' => ! empty($user['avatar_url']) ? (string) $user['avatar_url'] : null,
        ];
    }

    private function errorMessage($response, string $fallback): string
    {
        $json = $response->json();
        $err = $json['error'] ?? null;
        if (is_array($err)) {
            if (! empty($err['message'])) {
                return (string) $err['message'];
            }
            if (! empty($err['log_id'])) {
                return $fallback.' (log_id: '.$err['log_id'].')';
            }
        }

        return $fallback;
    }

    private function normalizePostedAt(mixed $raw): ?string
    {
        if (is_numeric($raw)) {
            try {
                return now()->createFromTimestamp((int) $raw)->toAtomString();
            } catch (\Throwable) {
                return null;
            }
        }

        if (is_string($raw) && trim($raw) !== '') {
            return $raw;
        }

        return null;
    }
}
