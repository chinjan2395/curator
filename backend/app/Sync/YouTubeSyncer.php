<?php

namespace App\Sync;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class YouTubeSyncer
{
    public function channels(SocialCredential $credential): array|JsonResponse
    {
        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'YouTube credential has expired or could not be refreshed. Reconnect YouTube in Credentials.',
            ], 422);
        }

        $listed = $this->listMineChannels($token);
        if ($listed instanceof JsonResponse) {
            return $listed;
        }

        return ['channels' => $listed];
    }

    public function test(SocialCredential $credential, string $channelIdOrHandle): array|JsonResponse
    {
        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'YouTube credential has expired or could not be refreshed. Reconnect YouTube in Credentials.',
            ], 422);
        }

        $resolved = $this->resolveChannelAndPlaylist($token, trim($channelIdOrHandle));
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $deny = $this->ensureChannelOwnedByToken($token, $resolved['channel_id']);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        return ['message' => 'YouTube connection successful.', 'channel_id' => $resolved['channel_id']];
    }

    public function sync(Feed $feed): JsonResponse
    {
        $credential = $feed->socialCredential;

        if (! $credential) {
            return response()->json(['message' => 'No credential attached to this feed. Connect YouTube and assign the credential to the feed.'], 422);
        }

        if (! $feed->youtube_channel_id) {
            return response()->json(['message' => 'youtube_channel_id is not set on this feed.'], 422);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json(['message' => 'YouTube credential has expired or could not be refreshed. Reconnect YouTube in Credentials.'], 422);
        }

        $resolved = $this->resolveChannelAndPlaylist($token, trim((string) $feed->youtube_channel_id));
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $deny = $this->ensureChannelOwnedByToken($token, $resolved['channel_id']);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        if (
            ! $feed->youtube_uploads_playlist_id
            || (string) $feed->youtube_uploads_playlist_id !== $resolved['uploads_playlist_id']
            || (string) $feed->youtube_channel_id !== $resolved['channel_id']
        ) {
            $feed->youtube_uploads_playlist_id = $resolved['uploads_playlist_id'];
            $feed->youtube_channel_id = $resolved['channel_id'];
            $feed->save();
        }

        $this->refreshFeedAccountLabel($feed, $token, $resolved['channel_id']);

        $response = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/playlistItems', [
            'part' => 'snippet,contentDetails',
            'playlistId' => (string) $feed->youtube_uploads_playlist_id,
            'maxResults' => 20,
        ]);

        if (! $response->ok()) {
            return response()->json(['message' => 'Failed to load uploads from YouTube.', 'error' => $response->json()], $response->status());
        }

        $created = 0;
        foreach ($response->json('items', []) as $item) {
            $videoId = $item['contentDetails']['videoId'] ?? null;
            if (! $videoId) {
                continue;
            }

            $snippet = $item['snippet'] ?? [];
            $title = $snippet['title'] ?? '';
            $description = $snippet['description'] ?? '';
            $publishedAt = $item['contentDetails']['videoPublishedAt'] ?? $snippet['publishedAt'] ?? null;
            $thumb = $snippet['thumbnails']['medium']['url']
                ?? $snippet['thumbnails']['high']['url']
                ?? $snippet['thumbnails']['default']['url']
                ?? null;

            Post::updateOrCreate(
                ['feed_id' => $feed->id, 'external_id' => $videoId],
                [
                    'title' => $title,
                    'content' => trim($title."\n\n".$description),
                    'thumbnail_url' => $thumb,
                    'video_url' => 'https://www.youtube.com/watch?v='.$videoId,
                    'posted_at' => $publishedAt,
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json(['message' => 'YouTube sync complete', 'created' => $created, 'last_synced_at' => $feed->last_synced_at]);
    }

    private function listMineChannels(string $token): array|JsonResponse
    {
        $channels = [];
        $pageToken = null;
        $first = true;

        do {
            $query = ['part' => 'snippet', 'mine' => 'true', 'maxResults' => 50];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $r = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', $query);
            if (! $r->ok()) {
                if ($first) {
                    return response()->json(['message' => 'Failed to load your YouTube channels.', 'error' => $r->json()], min(499, max(400, $r->status())));
                }
                break;
            }

            $first = false;
            foreach ($r->json('items', []) as $item) {
                $id = (string) ($item['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $sn = $item['snippet'] ?? [];
                $channels[] = [
                    'id' => $id,
                    'title' => (string) ($sn['title'] ?? ''),
                    'custom_url' => ! empty($sn['customUrl']) ? (string) $sn['customUrl'] : null,
                ];
            }

            $pageToken = $r->json('nextPageToken');
        } while ($pageToken);

        return $channels;
    }

    private function resolveChannelAndPlaylist(string $token, string $channelIdOrHandle): array|JsonResponse
    {
        $isHandle = str_starts_with($channelIdOrHandle, '@');
        $params = ['part' => 'contentDetails,snippet', 'maxResults' => 1];
        $params[$isHandle ? 'forHandle' : 'id'] = $channelIdOrHandle;

        $r = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', $params);

        if (! $r->ok()) {
            return response()->json(['message' => 'Failed to load channel details from YouTube.', 'error' => $r->json()], $r->status());
        }

        $items = $r->json('items', []);
        if (empty($items) && ! $isHandle) {
            $r2 = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'contentDetails,snippet', 'maxResults' => 1, 'forHandle' => $channelIdOrHandle,
            ]);
            if ($r2->ok()) {
                $items = $r2->json('items', []);
            }
        }

        if (empty($items)) {
            return response()->json(['message' => 'Channel not found. Check the channel ID (e.g. UC…) or handle (@name).'], 422);
        }

        $canonicalId = (string) ($items[0]['id'] ?? '');
        if ($canonicalId === '') {
            return response()->json(['message' => 'Unexpected YouTube API response (missing channel id).'], 422);
        }

        $uploads = $items[0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        if (! $uploads) {
            return response()->json(['message' => 'Could not determine uploads playlist for this channel.'], 422);
        }

        return ['channel_id' => $canonicalId, 'uploads_playlist_id' => (string) $uploads];
    }

    private function ensureChannelOwnedByToken(string $token, string $canonicalChannelId): ?JsonResponse
    {
        $listed = $this->listMineChannels($token);
        if ($listed instanceof JsonResponse) {
            return response()->json(['message' => 'Could not list YouTube channels for this account. Reconnect YouTube or grant channel access.'], 422);
        }

        $owned = array_column($listed, 'id');
        if (in_array($canonicalChannelId, $owned, true)) {
            return null;
        }

        return response()->json(['message' => 'This YouTube channel is not owned by the connected Google account. Only channels you manage can be synced.'], 422);
    }

    private function refreshFeedAccountLabel(Feed $feed, string $token, string $channelId): void
    {
        $r = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'snippet',
            'id' => $channelId,
            'maxResults' => 1,
        ]);

        if (! $r->ok()) {
            return;
        }

        $snippet = $r->json('items.0.snippet');
        if (! is_array($snippet)) {
            return;
        }

        $custom = isset($snippet['customUrl']) ? trim((string) $snippet['customUrl']) : '';
        $title = isset($snippet['title']) ? trim((string) $snippet['title']) : '';

        $label = '';
        if ($custom !== '') {
            $label = str_starts_with($custom, '@') ? $custom : '@'.$custom;
        } elseif ($title !== '') {
            $label = $title;
        }

        if ($label === '') {
            return;
        }

        $feed->source_account_label = $label;
        $feed->save();
    }
}
