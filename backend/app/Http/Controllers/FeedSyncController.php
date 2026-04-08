<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\OAuthAppConfig;
use App\Models\Post;
use App\Models\SocialCredential;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FeedSyncController extends Controller
{
    private const FACEBOOK_GRAPH_VERSION = 'v23.0';

    private const X_API_BASE = 'https://api.x.com/2';
    private const TIKTOK_API_BASE = 'https://open.tiktokapis.com/v2';
    private const TIKTOK_VIDEO_FIELDS = 'id,title,video_description,create_time,cover_image_url,share_url';

    private function authorizeWorkspace(Request $request, Workspace $workspace): void
    {
        if ($workspace->owner_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }

    private function ensureFeedInWorkspace(Workspace $workspace, Feed $feed): void
    {
        if ($feed->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    /**
     * Social credentials are per-user; a workspace feed must not use another user's tokens.
     */
    private function ensureFeedUsesOwnerCredential(Workspace $workspace, Feed $feed): ?JsonResponse
    {
        $cid = $feed->social_credential_id;
        if ($cid === null) {
            return null;
        }

        $cred = SocialCredential::query()->find($cid);
        if (! $cred || (int) $cred->user_id !== (int) $workspace->owner_id) {
            return response()->json([
                'message' => 'This feed references a credential that does not belong to the workspace owner. Re-save the feed with your own credential.',
            ], 422);
        }

        return null;
    }

    public function facebookPages(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
        ]);

        $credential = SocialCredential::where('id', $validated['social_credential_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $credential || $credential->provider !== 'facebook') {
            return response()->json([
                'message' => 'Facebook credential not found for this user.',
            ], 404);
        }

        $userToken = $credential->access_token;
        if (! $userToken) {
            return response()->json([
                'message' => 'Facebook credential token missing. Reconnect Facebook.',
            ], 422);
        }

        $resp = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts', [
            'fields' => 'id,name',
            'limit' => 100,
            'access_token' => $userToken,
        ]);

        if (! $resp->ok()) {
            $err = $resp->json('error', []);
            $msg = is_array($err) ? ($err['message'] ?? 'Failed to load accessible Pages.') : 'Failed to load accessible Pages.';

            return response()->json([
                'message' => $msg,
                'error' => $err,
            ], min(499, max(400, $resp->status())));
        }

        $pages = array_map(function ($p) {
            return [
                'id' => (string) ($p['id'] ?? ''),
                'name' => (string) ($p['name'] ?? ''),
            ];
        }, $resp->json('data', []));

        $pages = array_values(array_filter($pages, fn ($p) => $p['id'] !== ''));

        return response()->json([
            'pages' => $pages,
        ]);
    }

    public function youtubeChannels(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
        ]);

        $credential = SocialCredential::where('id', $validated['social_credential_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $credential || $credential->provider !== 'youtube') {
            return response()->json([
                'message' => 'YouTube credential not found for this user.',
            ], 404);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'YouTube credential has expired or could not be refreshed. Reconnect YouTube in Credentials.',
            ], 422);
        }

        $listed = $this->listMineYoutubeChannelsWithToken($token);
        if ($listed instanceof JsonResponse) {
            return $listed;
        }

        return response()->json([
            'channels' => $listed,
        ]);
    }

    public function twitterAccount(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
        ]);

        $credential = SocialCredential::where('id', $validated['social_credential_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $credential || $credential->provider !== 'twitter') {
            return response()->json([
                'message' => 'Twitter / X credential not found for this user.',
            ], 404);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'Twitter / X credential has expired or could not be refreshed. Reconnect Twitter in Credentials.',
            ], 422);
        }

        $resolved = $this->resolveTwitterUserForAuthenticatedAccount($token);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        return response()->json([
            'accounts' => [
                [
                    'id' => $resolved['id'],
                    'username' => $resolved['username'],
                    'name' => $resolved['name'],
                ],
            ],
        ]);
    }

    public function tiktokAccount(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
        ]);

        $credential = SocialCredential::where('id', $validated['social_credential_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $credential || $credential->provider !== 'tiktok') {
            return response()->json([
                'message' => 'TikTok credential not found for this user.',
            ], 404);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'TikTok credential has expired or could not be refreshed. Reconnect TikTok in Credentials.',
            ], 422);
        }

        $resolved = $this->resolveTikTokUserForAuthenticatedAccount($token);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        return response()->json([
            'accounts' => [
                [
                    'open_id' => $resolved['open_id'],
                    'username' => $resolved['username'],
                    'display_name' => $resolved['display_name'],
                    'avatar_url' => $resolved['avatar_url'],
                ],
            ],
        ]);
    }

    public function sync(Request $request, Workspace $workspace, Feed $feed)
    {
        $this->authorizeWorkspace($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);

        $credErr = $this->ensureFeedUsesOwnerCredential($workspace, $feed);
        if ($credErr instanceof JsonResponse) {
            return $credErr;
        }

        if ($feed->type === 'youtube') {
            return $this->syncYouTube($feed);
        }

        if ($feed->type === 'rss') {
            return $this->syncRss($feed);
        }

        if ($feed->type === 'facebook') {
            return $this->syncFacebook($feed);
        }

        if ($feed->type === 'twitter') {
            return $this->syncTwitter($feed);
        }

        if ($feed->type === 'tiktok') {
            return $this->syncTikTok($feed);
        }

        // Fallback: existing stub for other types (not in MVP)
        $count = (int) ($request->input('count', 8));
        $count = max(1, min($count, 30));

        $now = now();
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $externalId = $feed->type.'-'.Str::random(10);

            Post::create([
                'feed_id' => $feed->id,
                'title' => ucfirst($feed->type).' sample post',
                'content' => $this->sampleContent($feed->type),
                'thumbnail_url' => null,
                'video_url' => null,
                'posted_at' => $now->copy()->subMinutes($i * 37),
                'external_id' => $externalId,
                'status' => 'pending',
                'pinned' => false,
            ]);

            $created++;
        }

        $feed->update(['last_synced_at' => $now]);

        return response()->json([
            'message' => 'Sync complete (stub)',
            'created' => $created,
            'last_synced_at' => $feed->last_synced_at,
        ]);
    }

    private function syncRss(Feed $feed)
    {
        $url = trim((string) $feed->source_url);
        if ($url === '') {
            return response()->json([
                'message' => 'source_url is not set on this feed.',
            ], 422);
        }

        $resp = Http::timeout(15)
            ->accept('application/rss+xml, application/atom+xml, application/xml, text/xml, */*')
            ->get($url);
        if (! $resp->ok()) {
            return response()->json([
                'message' => 'Failed to load RSS feed.',
                'status' => $resp->status(),
            ], 422);
        }

        $items = $this->parseFeedXmlItems($resp->body());
        if (empty($items)) {
            return response()->json([
                'message' => 'Could not parse RSS/Atom feed (no items found).',
            ], 422);
        }

        $created = 0;
        foreach (array_slice($items, 0, 25) as $item) {
            $externalId = $item['external_id'];
            if (! $externalId) {
                continue;
            }

            Post::updateOrCreate(
                [
                    'feed_id' => $feed->id,
                    'external_id' => $externalId,
                ],
                [
                    'title' => $item['title'] ?? null,
                    'content' => $item['content'] ?? '',
                    'thumbnail_url' => $item['thumbnail_url'] ?? null,
                    'video_url' => $item['url'] ?? null,
                    'posted_at' => $item['posted_at'] ?? null,
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json([
            'message' => 'RSS sync complete',
            'created' => $created,
            'last_synced_at' => $feed->last_synced_at,
        ]);
    }

    private function parseFeedXmlItems(string $xml): array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (! $root) {
            return [];
        }

        $items = [];

        // RSS 2.0: <rss><channel><item>
        if (isset($root->channel->item)) {
            foreach ($root->channel->item as $item) {
                $items[] = $this->mapRssItem($item);
            }
            return array_values(array_filter($items, fn ($i) => ! empty($i['external_id'])));
        }

        // Atom: <feed><entry>
        if (isset($root->entry)) {
            foreach ($root->entry as $entry) {
                $items[] = $this->mapAtomEntry($entry);
            }
        }

        return array_values(array_filter($items, fn ($i) => ! empty($i['external_id'])));
    }

    private function extractFeedTitleFromXmlString(string $xml): ?string
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (! $root) {
            return null;
        }

        if (isset($root->channel->title)) {
            $t = trim((string) $root->channel->title);

            return $t !== '' ? $t : null;
        }

        if (isset($root->title)) {
            $t = trim((string) $root->title);

            return $t !== '' ? $t : null;
        }

        return null;
    }

    private function syncFacebook(Feed $feed)
    {
        $credential = $feed->socialCredential;

        if (! $credential || $credential->provider !== 'facebook') {
            return response()->json([
                'message' => 'No Facebook credential attached to this feed. Connect Facebook and assign it to the feed.',
            ], 422);
        }

        $pageId = $this->normalizeFacebookPageId(trim((string) $feed->facebook_page_id));
        if ($pageId === '') {
            return response()->json([
                'message' => 'facebook_page_id is not set on this feed.',
            ], 422);
        }

        $pageToken = $this->resolveFacebookPageAccessToken($credential, $pageId);
        if (! $pageToken) {
            return response()->json([
                'message' => 'Could not access this Page. Confirm the Page ID, that you manage the Page, and reconnect Facebook with Page permissions granted.',
            ], 422);
        }

        $fields = 'id,message,story,created_time,permalink_url,full_picture,attachments{media,subattachments}';
        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId.'/feed', [
            'fields' => $fields,
            'limit' => 25,
            'access_token' => $pageToken,
        ]);

        if (! $response->ok()) {
            $err = $response->json('error', []);
            $msg = is_array($err) ? ($err['message'] ?? 'Failed to load Facebook Page feed.') : 'Failed to load Facebook Page feed.';

            return response()->json([
                'message' => $msg,
                'error' => $err,
            ], min(499, max(400, $response->status())));
        }

        $items = $response->json('data', []);
        $created = 0;

        foreach ($items as $post) {
            $externalId = $post['id'] ?? null;
            if (! $externalId) {
                continue;
            }

            $message = trim((string) ($post['message'] ?? ''));
            $story = trim((string) ($post['story'] ?? ''));
            $body = $message !== '' ? $message : $story;
            $title = $body !== '' ? mb_substr($body, 0, 120) : 'Facebook post';

            $thumb = $this->facebookFeedItemThumbnail($post);
            $link = $post['permalink_url'] ?? null;
            $postedAt = $post['created_time'] ?? null;

            Post::updateOrCreate(
                [
                    'feed_id' => $feed->id,
                    'external_id' => $externalId,
                ],
                [
                    'title' => $title,
                    'content' => $body,
                    'thumbnail_url' => $thumb,
                    'video_url' => $link,
                    'posted_at' => $postedAt,
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json([
            'message' => 'Facebook sync complete',
            'created' => $created,
            'last_synced_at' => $feed->last_synced_at,
        ]);
    }

    private function syncTwitter(Feed $feed)
    {
        $credential = $feed->socialCredential;

        if (! $credential || $credential->provider !== 'twitter') {
            return response()->json([
                'message' => 'No Twitter / X credential attached to this feed. Connect X and assign the credential to the feed.',
            ], 422);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'Twitter / X credential has expired or could not be refreshed. Reconnect Twitter in Credentials.',
            ], 422);
        }

        $resolved = $this->resolveTwitterUserForAuthenticatedAccount($token);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $userId = $resolved['id'];

        $resp = Http::withToken($token)->timeout(20)->get(self::X_API_BASE.'/users/'.$userId.'/tweets', [
            'max_results' => 25,
            'tweet.fields' => 'created_at,text,note_tweet,attachments',
            'expansions' => 'attachments.media_keys',
            'media.fields' => 'preview_image_url,type,url',
            'exclude' => 'retweets',
        ]);

        if (! $resp->ok()) {
            return response()->json([
                'message' => $this->xApiErrorMessage($resp, 'Failed to load posts from X.'),
                'error' => $resp->json(),
            ], min(499, max(400, $resp->status())));
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

            $body = '';
            if (! empty($tweet['note_tweet']['text'])) {
                $body = trim((string) $tweet['note_tweet']['text']);
            } else {
                $body = trim((string) ($tweet['text'] ?? ''));
            }
            $title = $body !== '' ? mb_substr($body, 0, 120) : 'X post';

            $thumb = null;
            foreach ($tweet['attachments']['media_keys'] ?? [] as $mk) {
                $media = $mediaByKey[$mk] ?? null;
                if (! $media) {
                    continue;
                }
                $thumb = $media['preview_image_url'] ?? $media['url'] ?? null;
                if ($thumb) {
                    break;
                }
            }

            $permalink = 'https://x.com/i/web/status/'.rawurlencode((string) $externalId);

            Post::updateOrCreate(
                [
                    'feed_id' => $feed->id,
                    'external_id' => (string) $externalId,
                ],
                [
                    'title' => $title,
                    'content' => $body,
                    'thumbnail_url' => $thumb,
                    'video_url' => $permalink,
                    'posted_at' => $tweet['created_at'] ?? null,
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json([
            'message' => 'X sync complete',
            'created' => $created,
            'last_synced_at' => $feed->last_synced_at,
        ]);
    }

    private function syncTikTok(Feed $feed)
    {
        $credential = $feed->socialCredential;

        if (! $credential || $credential->provider !== 'tiktok') {
            return response()->json([
                'message' => 'No TikTok credential attached to this feed. Connect TikTok and assign the credential to the feed.',
            ], 422);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'TikTok credential has expired or could not be refreshed. Reconnect TikTok in Credentials.',
            ], 422);
        }

        $user = $this->resolveTikTokUserForAuthenticatedAccount($token);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $resp = Http::withToken($token)
            ->timeout(20)
            ->acceptJson()
            ->post(self::TIKTOK_API_BASE.'/video/list/', [
                'max_count' => 20,
                'fields' => self::TIKTOK_VIDEO_FIELDS,
            ]);

        if (! $resp->ok()) {
            return response()->json([
                'message' => $this->tikTokApiErrorMessage($resp, 'Failed to load videos from TikTok.'),
                'error' => $resp->json(),
            ], min(499, max(400, $resp->status())));
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
            $body = trim($title."\n\n".$description);
            if ($body === '') {
                $body = 'TikTok video';
            }
            $resolvedTitle = $title !== '' ? $title : mb_substr($description, 0, 120);
            if ($resolvedTitle === '') {
                $resolvedTitle = 'TikTok video';
            }

            Post::updateOrCreate(
                [
                    'feed_id' => $feed->id,
                    'external_id' => $externalId,
                ],
                [
                    'title' => $resolvedTitle,
                    'content' => $body,
                    'thumbnail_url' => $video['cover_image_url'] ?? null,
                    'video_url' => $video['share_url'] ?? null,
                    'posted_at' => $this->normalizeTikTokPostedAt($video['create_time'] ?? null),
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json([
            'message' => 'TikTok sync complete',
            'created' => $created,
            'last_synced_at' => $feed->last_synced_at,
            'username' => $user['username'] ?? null,
        ]);
    }

    /**
     * Only the X account tied to the OAuth token may be synced (no arbitrary handles).
     *
     * @return array{id: string, username: string}|JsonResponse
     */
    /**
     * @return array{id: string, username: string, name: string}|JsonResponse
     */
    private function resolveTwitterUserForAuthenticatedAccount(string $token)
    {
        $me = Http::withToken($token)->get(self::X_API_BASE.'/users/me', [
            'user.fields' => 'id,username,name',
        ]);

        if (! $me->ok()) {
            return response()->json([
                'message' => $this->xApiErrorMessage($me, 'Could not load your X account (users/me).'),
                'error' => $me->json(),
            ], min(499, max(400, $me->status())));
        }

        $data = $me->json('data');
        $id = $data['id'] ?? null;
        if (! $id) {
            return response()->json([
                'message' => 'Unexpected X API response for users/me.',
                'error' => $me->json(),
            ], 422);
        }

        return [
            'id' => (string) $id,
            'username' => (string) ($data['username'] ?? ''),
            'name' => (string) ($data['name'] ?? ''),
        ];
    }

    /**
     * Channels the authenticated Google account can manage (same source as ownership checks).
     *
     * @return array<int, array{id: string, title: string, custom_url: string|null}>|JsonResponse
     */
    private function listMineYoutubeChannelsWithToken(string $token): array|JsonResponse
    {
        $channels = [];
        $pageToken = null;
        $first = true;

        do {
            $query = [
                'part' => 'snippet',
                'mine' => 'true',
                'maxResults' => 50,
            ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $r = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', $query);
            if (! $r->ok()) {
                if ($first) {
                    return response()->json([
                        'message' => 'Failed to load your YouTube channels.',
                        'error' => $r->json(),
                    ], min(499, max(400, $r->status())));
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

    /**
     * @return array<int, string>
     */
    private function fetchYoutubeChannelIdsOwnedByToken(string $token): array
    {
        $listed = $this->listMineYoutubeChannelsWithToken($token);
        if ($listed instanceof JsonResponse) {
            return [];
        }

        return array_values(array_unique(array_column($listed, 'id')));
    }

    /**
     * Resolve a channel id or handle to canonical channel id and uploads playlist id.
     *
     * @return array{channel_id: string, uploads_playlist_id: string}|JsonResponse
     */
    private function resolveYoutubeChannelAndUploadsPlaylist(string $token, string $channelIdOrHandle): array|JsonResponse
    {
        $channelIdOrHandle = trim($channelIdOrHandle);
        $isHandle = str_starts_with($channelIdOrHandle, '@');
        $params = [
            'part' => 'contentDetails,snippet',
            'maxResults' => 1,
        ];
        if ($isHandle) {
            $params['forHandle'] = $channelIdOrHandle;
        } else {
            $params['id'] = $channelIdOrHandle;
        }

        $channelResponse = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', $params);

        if (! $channelResponse->ok()) {
            return response()->json([
                'message' => 'Failed to load channel details from YouTube.',
                'error' => $channelResponse->json(),
            ], $channelResponse->status());
        }

        $items = $channelResponse->json('items', []);
        if (empty($items) && ! $isHandle) {
            $channelResponse = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'contentDetails,snippet',
                'maxResults' => 1,
                'forHandle' => $channelIdOrHandle,
            ]);
            if ($channelResponse->ok()) {
                $items = $channelResponse->json('items', []);
            }
        }

        if (empty($items)) {
            return response()->json([
                'message' => 'Channel not found. Check the channel ID (e.g. UC…) or handle (@name).',
            ], 422);
        }

        $canonicalId = (string) ($items[0]['id'] ?? '');
        if ($canonicalId === '') {
            return response()->json([
                'message' => 'Unexpected YouTube API response (missing channel id).',
            ], 422);
        }

        $uploads = $items[0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        if (! $uploads) {
            return response()->json([
                'message' => 'Could not determine uploads playlist for this channel.',
            ], 422);
        }

        return [
            'channel_id' => $canonicalId,
            'uploads_playlist_id' => (string) $uploads,
        ];
    }

    /**
     * @return JsonResponse|null Returns a JSON error response if the channel is not owned by the token; otherwise null.
     */
    private function ensureYoutubeChannelOwnedByToken(string $token, string $canonicalChannelId): ?JsonResponse
    {
        $owned = $this->fetchYoutubeChannelIdsOwnedByToken($token);
        sort($owned);
        if ($owned === []) {
            return response()->json([
                'message' => 'Could not list YouTube channels for this account. Reconnect YouTube or grant channel access.',
            ], 422);
        }

        $canonicalChannelId = (string) $canonicalChannelId;
        foreach ($owned as $id) {
            if ($id === $canonicalChannelId) {
                return null;
            }
        }

        return response()->json([
            'message' => 'This YouTube channel is not owned by the connected Google account. Only channels you manage can be synced.',
        ], 422);
    }

    private function xApiErrorMessage($response, string $fallback): string
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

    /**
     * @return array{open_id: string, username: string, display_name: string, avatar_url: string|null}|JsonResponse
     */
    private function resolveTikTokUserForAuthenticatedAccount(string $token)
    {
        $resp = Http::withToken($token)
            ->acceptJson()
            ->get(self::TIKTOK_API_BASE.'/user/info/', [
                'fields' => 'open_id,union_id,display_name,username,avatar_url',
            ]);

        if (! $resp->ok()) {
            return response()->json([
                'message' => $this->tikTokApiErrorMessage($resp, 'Could not load your TikTok account.'),
                'error' => $resp->json(),
            ], min(499, max(400, $resp->status())));
        }

        $user = $resp->json('data.user', []);
        $openId = (string) ($user['open_id'] ?? '');
        if ($openId === '') {
            return response()->json([
                'message' => 'Unexpected TikTok API response for user info.',
                'error' => $resp->json(),
            ], 422);
        }

        return [
            'open_id' => $openId,
            'username' => (string) ($user['username'] ?? ''),
            'display_name' => (string) ($user['display_name'] ?? ''),
            'avatar_url' => ! empty($user['avatar_url']) ? (string) $user['avatar_url'] : null,
        ];
    }

    private function tikTokApiErrorMessage($response, string $fallback): string
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

    private function normalizeTikTokPostedAt(mixed $raw): ?string
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

    private function normalizeFacebookPageId(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('#[?&]id=(\d+)#', $raw, $m)) {
            return $m[1];
        }
        // Extract the first "long" numeric id from a typical facebook.com URL.
        // Use a delimiter that won't conflict with '#...#' in the pattern.
        if (preg_match('~facebook\.com/(?:[^0-9]+/)?([0-9]{10,})~i', $raw, $m)) {
            return $m[1];
        }

        return $raw;
    }

    private function resolveFacebookPageAccessToken(SocialCredential $credential, string $pageId): ?string
    {
        $userToken = $credential->access_token;
        if (! $userToken) {
            return null;
        }

        // Prefer the direct Page access token resolution endpoint.
        // This often returns the correct Page token for page-level endpoints.
        $direct = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId, [
            'fields' => 'access_token',
            'access_token' => $userToken,
        ]);
        if ($direct->ok()) {
            $token = $direct->json('access_token');
            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts', [
            'fields' => 'id,access_token',
            'access_token' => $userToken,
        ]);

        if (! $response->ok()) {
            return null;
        }

        foreach ($response->json('data', []) as $page) {
            if ((string) ($page['id'] ?? '') === (string) $pageId) {
                return $page['access_token'] ?? null;
            }
        }

        return null;
    }

    /**
     * Resolve Page access token for a given Page ID.
     *
     * Some Graph versions/environments do not support requesting `perms` from /me/accounts.
     * For that reason we only resolve id+access_token here.
     *
     * @return array{access_token: string|null, accounts_count: int}|null
     */
    private function resolveFacebookPageAccessTokenWithPerms(SocialCredential $credential, string $pageId): ?array
    {
        $userToken = $credential->access_token;
        if (! $userToken) {
            return null;
        }

        $direct = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId, [
            'fields' => 'access_token',
            'access_token' => $userToken,
        ]);
        if ($direct->ok()) {
            $token = $direct->json('access_token');
            if (is_string($token) && $token !== '') {
                return [
                    'access_token' => $token,
                    'accounts_count' => null,
                ];
            }
        }

        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts', [
            'fields' => 'id,access_token',
            'access_token' => $userToken,
        ]);

        if (! $response->ok()) {
            $err = $response->json('error', []);
            return [
                'access_token' => null,
                'accounts_count' => 0,
                'accounts_error' => is_array($err) ? $err : ['message' => 'Failed to load /me/accounts'],
                'accounts_status' => $response->status(),
            ];
        }

        $accountsData = $response->json('data', []);
        $accountsCount = is_array($accountsData) ? count($accountsData) : 0;

        foreach ($response->json('data', []) as $page) {
            if ((string) ($page['id'] ?? '') !== (string) $pageId) {
                continue;
            }

            return [
                'access_token' => $page['access_token'] ?? null,
                'accounts_count' => $accountsCount,
            ];
        }

        return [
            'access_token' => null,
            'accounts_count' => $accountsCount,
            'accounts_error' => null,
            'accounts_status' => $response->status(),
        ];
    }

    private function facebookFeedItemThumbnail(array $post): ?string
    {
        if (! empty($post['full_picture'])) {
            return $post['full_picture'];
        }

        $attachments = $post['attachments']['data'] ?? [];
        foreach ($attachments as $att) {
            $src = $att['media']['image']['src'] ?? null;
            if ($src) {
                return $src;
            }
            foreach ($att['subattachments']['data'] ?? [] as $sub) {
                $subSrc = $sub['media']['image']['src'] ?? null;
                if ($subSrc) {
                    return $subSrc;
                }
            }
        }

        return null;
    }

    private function mapRssItem(\SimpleXMLElement $item): array
    {
        $title = trim((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));
        $guid = trim((string) ($item->guid ?? ''));
        $desc = (string) ($item->description ?? '');
        $contentEncoded = '';

        $ns = $item->getNamespaces(true);
        if (isset($ns['content'])) {
            $contentEncoded = (string) ($item->children($ns['content'])->encoded ?? '');
        }

        $body = trim(strip_tags($contentEncoded !== '' ? $contentEncoded : $desc));
        $pubDate = trim((string) ($item->pubDate ?? ''));
        $postedAt = $pubDate !== '' ? date(DATE_ATOM, strtotime($pubDate)) : null;

        $thumb = null;
        if (isset($ns['media'])) {
            $media = $item->children($ns['media']);
            $thumb = (string) ($media->thumbnail['url'] ?? '') ?: (string) ($media->content['url'] ?? '');
        }
        if (! $thumb && isset($item->enclosure)) {
            $type = (string) ($item->enclosure['type'] ?? '');
            if (str_starts_with($type, 'image/')) {
                $thumb = (string) ($item->enclosure['url'] ?? '');
            }
        }

        $externalId = $guid !== '' ? $guid : ($link !== '' ? $link : ($title !== '' ? sha1($title.$body) : null));

        return [
            'external_id' => $externalId,
            'title' => $title !== '' ? $title : null,
            'content' => $body,
            'url' => $link !== '' ? $link : null,
            'thumbnail_url' => $thumb ?: null,
            'posted_at' => $postedAt,
        ];
    }

    private function mapAtomEntry(\SimpleXMLElement $entry): array
    {
        $title = trim((string) ($entry->title ?? ''));
        $id = trim((string) ($entry->id ?? ''));
        $updated = trim((string) ($entry->updated ?? ''));
        $published = trim((string) ($entry->published ?? ''));

        $link = null;
        if (isset($entry->link)) {
            foreach ($entry->link as $l) {
                $rel = (string) ($l['rel'] ?? '');
                if ($rel === '' || $rel === 'alternate') {
                    $link = (string) ($l['href'] ?? '') ?: $link;
                }
            }
        }

        $content = (string) ($entry->content ?? '');
        $summary = (string) ($entry->summary ?? '');
        $body = trim(strip_tags($content !== '' ? $content : $summary));

        $postedAt = $published !== '' ? $published : ($updated !== '' ? $updated : null);
        $externalId = $id !== '' ? $id : ($link ?: ($title !== '' ? sha1($title.$body) : null));

        return [
            'external_id' => $externalId,
            'title' => $title !== '' ? $title : null,
            'content' => $body,
            'url' => $link,
            'thumbnail_url' => null,
            'posted_at' => $postedAt,
        ];
    }

    private function syncYouTube(Feed $feed)
    {
        $credential = $feed->socialCredential;

        if (! $credential) {
            return response()->json([
                'message' => 'No credential attached to this feed. Connect YouTube and assign the credential to the feed.',
            ], 422);
        }

        if (! $feed->youtube_channel_id) {
            return response()->json([
                'message' => 'youtube_channel_id is not set on this feed.',
            ], 422);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'YouTube credential has expired or could not be refreshed. Reconnect YouTube in Credentials.',
            ], 422);
        }

        $resolved = $this->resolveYoutubeChannelAndUploadsPlaylist($token, trim((string) $feed->youtube_channel_id));
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $deny = $this->ensureYoutubeChannelOwnedByToken($token, $resolved['channel_id']);
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

        $playlistId = (string) $feed->youtube_uploads_playlist_id;

        $response = Http::withToken($token)->get('https://www.googleapis.com/youtube/v3/playlistItems', [
            'part' => 'snippet,contentDetails',
            'playlistId' => $playlistId,
            'maxResults' => 20,
        ]);

        if (! $response->ok()) {
            return response()->json([
                'message' => 'Failed to load uploads from YouTube.',
                'error' => $response->json(),
            ], $response->status());
        }

        $items = $response->json('items', []);
        $created = 0;

        foreach ($items as $item) {
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

            $content = trim($title."\n\n".$description);

            Post::updateOrCreate(
                [
                    'feed_id' => $feed->id,
                    'external_id' => $videoId,
                ],
                [
                    'title' => $title,
                    'content' => $content,
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

        return response()->json([
            'message' => 'YouTube sync complete',
            'created' => $created,
            'last_synced_at' => $feed->last_synced_at,
        ]);
    }

    public function testYouTube(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
            'youtube_channel_id' => ['required', 'string', 'max:255'],
        ]);

        $credential = SocialCredential::where('id', $validated['social_credential_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $credential) {
            return response()->json([
                'message' => 'Credential not found for this user.',
            ], 404);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'YouTube credential has expired or could not be refreshed. Reconnect YouTube in Credentials.',
            ], 422);
        }

        $channelIdOrHandle = trim($validated['youtube_channel_id']);

        $resolved = $this->resolveYoutubeChannelAndUploadsPlaylist($token, $channelIdOrHandle);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $deny = $this->ensureYoutubeChannelOwnedByToken($token, $resolved['channel_id']);
        if ($deny instanceof JsonResponse) {
            return $deny;
        }

        return response()->json([
            'message' => 'YouTube connection successful.',
            'channel_id' => $resolved['channel_id'],
        ]);
    }

    public function testRss(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'source_url' => ['required', 'string', 'max:500'],
        ]);

        $url = trim($validated['source_url']);
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json([
                'message' => 'Enter a valid RSS URL.',
            ], 422);
        }

        $resp = Http::timeout(15)
            ->accept('application/rss+xml, application/atom+xml, application/xml, text/xml, */*')
            ->get($url);
        if (! $resp->ok()) {
            return response()->json([
                'message' => 'Failed to load RSS feed.',
                'status' => $resp->status(),
            ], 422);
        }

        $body = $resp->body();
        $items = $this->parseFeedXmlItems($body);
        if (empty($items)) {
            return response()->json([
                'message' => 'Could not parse RSS/Atom feed (no items found).',
            ], 422);
        }

        $feedTitle = $this->extractFeedTitleFromXmlString($body);

        return response()->json([
            'message' => 'RSS connection successful.',
            'feed_title' => $feedTitle,
            'item_count' => count($items),
            'sample_title' => $items[0]['title'] ?? null,
        ]);
    }

    public function testFacebook(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
            'facebook_page_id' => ['required', 'string', 'max:255'],
        ]);

        $credential = SocialCredential::where('id', $validated['social_credential_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $credential || $credential->provider !== 'facebook') {
            return response()->json([
                'message' => 'Facebook credential not found for this user.',
            ], 404);
        }

        $pageId = $this->normalizeFacebookPageId(trim($validated['facebook_page_id']));
        if ($pageId === '') {
            return response()->json([
                'message' => 'Enter a Facebook Page ID.',
            ], 422);
        }

        // Debug: what permissions does the current *user* token actually have?
        $userToken = $credential->access_token;
        $mePermissions = [];
        if (! empty($userToken)) {
            $permResp = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/permissions', [
                'access_token' => $userToken,
            ]);
            if ($permResp->ok()) {
                $mePermissionsRaw = $permResp->json('data', []);
                if (is_array($mePermissionsRaw)) {
                    $mePermissions = array_values(array_map(function ($p) {
                        return [
                            'permission' => (string) ($p['permission'] ?? ''),
                            'status' => (string) ($p['status'] ?? ''),
                        ];
                    }, $mePermissionsRaw));
                }
            }
        }

        $resolved = $this->resolveFacebookPageAccessTokenWithPerms($credential, $pageId);
        $pageToken = $resolved['access_token'] ?? null;
        if (! $pageToken) {
            return response()->json([
                'message' => 'Could not access this Page. Confirm the Page ID, that you manage the Page, and reconnect Facebook with Page permissions granted.',
                'debug_page_token_resolution' => [
                    'page_id_input' => $validated['facebook_page_id'],
                    'normalized_page_id' => $pageId,
                    'debug_accounts_status' => $resolved['accounts_status'] ?? null,
                    'debug_accounts_error' => $resolved['accounts_error'] ?? null,
                    'debug_accounts_count' => $resolved['accounts_count'] ?? null,
                    'debug_me_permissions' => $mePermissions,
                ],
            ], 422);
        }

        // Debug token scopes for user + page tokens (no secrets returned).
        $oauth = OAuthAppConfig::query()
            ->where('user_id', $request->user()->id)
            ->where('provider', 'facebook')
            ->first();
        $debugUser = null;
        $debugPage = null;
        if ($oauth?->client_id && $oauth?->client_secret) {
            $appToken = $oauth->client_id.'|'.$oauth->client_secret;
            $debugUser = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/debug_token', [
                'input_token' => $userToken,
                'access_token' => $appToken,
            ])->json('data');
            $debugPage = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/debug_token', [
                'input_token' => $pageToken,
                'access_token' => $appToken,
            ])->json('data');
        }

        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId.'/feed', [
            'fields' => 'id,message',
            'limit' => 1,
            'access_token' => $pageToken,
        ]);

        if (! $response->ok()) {
            $err = $response->json('error', []);
            $msg = is_array($err) ? ($err['message'] ?? 'Failed to load Page feed.') : 'Failed to load Page feed.';

            return response()->json([
                'message' => $msg,
                'error' => $err,
                'debug_me_permissions' => $mePermissions,
                'debug_token_user' => $debugUser ? ['scopes' => $debugUser['scopes'] ?? null, 'type' => $debugUser['type'] ?? null] : null,
                'debug_token_page' => $debugPage ? ['scopes' => $debugPage['scopes'] ?? null, 'type' => $debugPage['type'] ?? null] : null,
            ], min(499, max(400, $response->status())));
        }

        return response()->json([
            'message' => 'Facebook connection successful.',
            'page_id' => $pageId,
        ]);
    }

    public function testTwitter(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
        ]);

        $credential = SocialCredential::where('id', $validated['social_credential_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $credential || $credential->provider !== 'twitter') {
            return response()->json([
                'message' => 'Twitter / X credential not found for this user.',
            ], 404);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'Twitter / X credential has expired or could not be refreshed. Reconnect Twitter in Credentials.',
            ], 422);
        }

        $resolved = $this->resolveTwitterUserForAuthenticatedAccount($token);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $userId = $resolved['id'];

        $resp = Http::withToken($token)->get(self::X_API_BASE.'/users/'.$userId.'/tweets', [
            'max_results' => 5,
            'tweet.fields' => 'created_at,text',
            'exclude' => 'retweets',
        ]);

        if (! $resp->ok()) {
            return response()->json([
                'message' => $this->xApiErrorMessage($resp, 'Failed to load posts from X.'),
                'error' => $resp->json(),
            ], min(499, max(400, $resp->status())));
        }

        return response()->json([
            'message' => 'X connection successful.',
            'user_id' => $userId,
            'username' => $resolved['username'] ?? null,
            'sample_count' => count($resp->json('data', [])),
        ]);
    }

    public function testTikTok(Request $request, Workspace $workspace)
    {
        $this->authorizeWorkspace($request, $workspace);

        $validated = $request->validate([
            'social_credential_id' => ['required', 'integer', 'exists:social_credentials,id'],
        ]);

        $credential = SocialCredential::where('id', $validated['social_credential_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $credential || $credential->provider !== 'tiktok') {
            return response()->json([
                'message' => 'TikTok credential not found for this user.',
            ], 404);
        }

        $token = $credential->getValidAccessToken();
        if (! $token) {
            return response()->json([
                'message' => 'TikTok credential has expired or could not be refreshed. Reconnect TikTok in Credentials.',
            ], 422);
        }

        $user = $this->resolveTikTokUserForAuthenticatedAccount($token);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $resp = Http::withToken($token)
            ->timeout(20)
            ->acceptJson()
            ->post(self::TIKTOK_API_BASE.'/video/list/', [
                'max_count' => 5,
                'fields' => self::TIKTOK_VIDEO_FIELDS,
            ]);

        if (! $resp->ok()) {
            return response()->json([
                'message' => $this->tikTokApiErrorMessage($resp, 'Failed to load videos from TikTok.'),
                'error' => $resp->json(),
            ], min(499, max(400, $resp->status())));
        }

        $videos = $resp->json('data.videos', []);
        if (! is_array($videos)) {
            $videos = [];
        }

        return response()->json([
            'message' => 'TikTok connection successful.',
            'open_id' => $user['open_id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'sample_count' => count($videos),
        ]);
    }

    private function sampleContent(string $type): string
    {
        $examples = [
            'instagram' => [
                'New drop: behind the scenes from today’s shoot. #bts #studio',
                'Quick carousel from the event—crowd energy was unreal.',
            ],
            'youtube' => [
                'New video is live: “How we built the social wall (v1)”',
                'Short: 3 UX details that make dashboards feel premium.',
            ],
            'rss' => [
                'Blog: UGC best practices for moderation and brand safety.',
                'Release notes: Feed filters + pinning are now available.',
            ],
            'twitter' => [
                'Shipping a new curation flow today. Small UI details matter.',
                'Poll: Which sources do you want next—TikTok or LinkedIn?',
            ],
            'tiktok' => [
                'Fast cut: product demo in 15 seconds. Thoughts?',
                'Creator spotlight: top community clips of the week.',
            ],
            'facebook' => [
                'Community update: new templates are available in the dashboard.',
                'Customer story: how a brand curated 5k posts safely.',
            ],
            'other' => [
                'Sample post generated for this feed.',
                'Another sample post to help test curation.',
            ],
        ];

        $key = array_key_exists($type, $examples) ? $type : 'other';
        return $examples[$key][array_rand($examples[$key])];
    }
}

