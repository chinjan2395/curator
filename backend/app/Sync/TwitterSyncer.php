<?php

namespace App\Sync;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TwitterSyncer
{
    private const X_API_BASE = 'https://api.x.com/2';

    public function account(SocialCredential $credential): array|JsonResponse
    {
        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'Twitter / X credential has expired or could not be refreshed. Reconnect Twitter in Credentials.'], 422);
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
            return response()->json(['message' => 'Twitter / X credential has expired or could not be refreshed. Reconnect Twitter in Credentials.'], 422);
        }

        $resolved = $this->resolveMe($token);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $resp = Http::withToken($token)->get(self::X_API_BASE.'/users/'.$resolved['id'].'/tweets', [
            'max_results' => 5,
            'tweet.fields' => 'created_at,text',
            'exclude' => 'retweets',
        ]);

        if (! $resp->ok()) {
            return response()->json(['message' => $this->errorMessage($resp, 'Failed to load posts from X.'), 'error' => $resp->json()], min(499, max(400, $resp->status())));
        }

        return ['message' => 'X connection successful.', 'user_id' => $resolved['id'], 'username' => $resolved['username'] ?? null, 'sample_count' => count($resp->json('data', []))];
    }

    public function sync(Feed $feed): JsonResponse
    {
        $credential = $feed->socialCredential;

        if (! $credential || $credential->provider !== 'twitter') {
            return response()->json(['message' => 'No Twitter / X credential attached to this feed. Connect X and assign the credential to the feed.'], 422);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'Twitter / X credential has expired or could not be refreshed. Reconnect Twitter in Credentials.'], 422);
        }

        $resolved = $this->resolveMe($token);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $uname = trim((string) ($resolved['username'] ?? ''));
        if ($uname !== '') {
            $feed->twitter_username = ltrim($uname, '@');
            $feed->source_account_label = '@'.ltrim($uname, '@');
            $feed->save();
        }

        $resp = Http::withToken($token)->timeout(20)->get(self::X_API_BASE.'/users/'.$resolved['id'].'/tweets', [
            'max_results' => 25,
            'tweet.fields' => 'created_at,text,note_tweet,attachments',
            'expansions' => 'attachments.media_keys',
            'media.fields' => 'preview_image_url,type,url',
            'exclude' => 'retweets',
        ]);

        if (! $resp->ok()) {
            return response()->json(['message' => $this->errorMessage($resp, 'Failed to load posts from X.'), 'error' => $resp->json()], min(499, max(400, $resp->status())));
        }

        $mediaByKey = [];
        foreach ($resp->json('includes.media', []) as $m) {
            if (! empty($m['media_key'])) {
                $mediaByKey[$m['media_key']] = $m;
            }
        }

        $created = 0;
        foreach ($resp->json('data', []) as $tweet) {
            $externalId = $tweet['id'] ?? null;
            if (! $externalId) {
                continue;
            }

            $body = ! empty($tweet['note_tweet']['text'])
                ? trim((string) $tweet['note_tweet']['text'])
                : trim((string) ($tweet['text'] ?? ''));
            $title = $body !== '' ? mb_substr($body, 0, 120) : 'X post';

            $thumb = null;
            foreach ($tweet['attachments']['media_keys'] ?? [] as $mk) {
                $media = $mediaByKey[$mk] ?? null;
                if ($media) {
                    $thumb = $media['preview_image_url'] ?? $media['url'] ?? null;
                    if ($thumb) {
                        break;
                    }
                }
            }

            Post::updateOrCreate(
                ['feed_id' => $feed->id, 'external_id' => (string) $externalId],
                [
                    'title' => $title,
                    'content' => $body,
                    'thumbnail_url' => $thumb,
                    'video_url' => 'https://x.com/i/web/status/'.rawurlencode((string) $externalId),
                    'posted_at' => $tweet['created_at'] ?? null,
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json(['message' => 'X sync complete', 'created' => $created, 'last_synced_at' => $feed->last_synced_at]);
    }

    private function resolveMe(string $token): array|JsonResponse
    {
        $me = Http::withToken($token)->get(self::X_API_BASE.'/users/me', ['user.fields' => 'id,username,name']);

        if (! $me->ok()) {
            return response()->json(['message' => $this->errorMessage($me, 'Could not load your X account (users/me).'), 'error' => $me->json()], min(499, max(400, $me->status())));
        }

        $data = $me->json('data');
        $id = $data['id'] ?? null;
        if (! $id) {
            return response()->json(['message' => 'Unexpected X API response for users/me.', 'error' => $me->json()], 422);
        }

        return ['id' => (string) $id, 'username' => (string) ($data['username'] ?? ''), 'name' => (string) ($data['name'] ?? '')];
    }

    private function errorMessage($response, string $fallback): string
    {
        $json = $response->json();
        $errors = $json['errors'] ?? null;
        if (is_array($errors) && isset($errors[0]) && is_array($errors[0])) {
            if (! empty($errors[0]['message'])) {
                return (string) $errors[0]['message'];
            }
            if (! empty($errors[0]['detail'])) {
                return (string) $errors[0]['detail'];
            }
        }
        $title = $json['title'] ?? null;
        if (is_string($title) && $title !== '') {
            return $title;
        }

        return $fallback;
    }
}
