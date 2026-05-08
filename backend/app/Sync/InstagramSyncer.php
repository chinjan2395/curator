<?php

namespace App\Sync;

use App\Models\Feed;
use App\Support\PostSyncUpsert;
use App\Models\SocialCredential;
use App\Support\OAuthAppConfigResolver;
use App\Sync\Concerns\ResolvesFacebookPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class InstagramSyncer
{
    use ResolvesFacebookPage;

    public function accounts(SocialCredential $credential): array|JsonResponse
    {
        $userToken = $credential->access_token;
        if (! $userToken) {
            return response()->json(['message' => 'Instagram credential token missing. Reconnect Instagram.'], 422);
        }

        $accountsOut = [];
        $nextUrl = 'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts';
        $query = [
            'fields' => 'id,name,instagram_business_account{id,username}',
            'limit' => 100,
            'access_token' => $userToken,
        ];

        while ($nextUrl !== null) {
            $resp = $nextUrl === 'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts'
                ? Http::get($nextUrl, $query)
                : Http::get($nextUrl);

            if (! $resp->ok()) {
                $err = $resp->json('error', []);
                $msg = is_array($err) ? ($err['message'] ?? 'Failed to load Facebook Pages.') : 'Failed to load Facebook Pages.';

                return response()->json(['message' => $msg, 'error' => $err], min(499, max(400, $resp->status())));
            }

            foreach ($resp->json('data', []) as $row) {
                $ig = $row['instagram_business_account'] ?? null;
                if (! is_array($ig)) {
                    continue;
                }
                $igId = (string) ($ig['id'] ?? '');
                if ($igId === '') {
                    continue;
                }
                $accountsOut[] = [
                    'facebook_page_id' => (string) ($row['id'] ?? ''),
                    'facebook_page_name' => (string) ($row['name'] ?? ''),
                    'instagram_business_account_id' => $igId,
                    'instagram_username' => (string) ($ig['username'] ?? ''),
                ];
            }

            $next = $resp->json('paging.next');
            $nextUrl = is_string($next) && $next !== '' ? $next : null;
        }

        return ['accounts' => $accountsOut];
    }

    public function test(SocialCredential $credential, string $pageIdRaw, string $igUserId, int $userId): array|JsonResponse
    {
        $pageId = $this->normalizeFacebookPageId($pageIdRaw);
        if ($pageId === '') {
            return response()->json(['message' => 'Enter a valid Facebook Page ID for this Instagram account.'], 422);
        }

        if (trim($igUserId) === '') {
            return response()->json(['message' => 'Enter an Instagram Business account ID.'], 422);
        }

        $userToken = $credential->access_token;
        $mePermissions = $this->fetchMePermissions($userToken);
        $resolved = $this->resolveFacebookPageAccessTokenWithPerms($credential, $pageId) ?? [];
        $pageToken = $resolved['access_token'] ?? null;

        if (! $pageToken) {
            return response()->json([
                'message' => 'Could not access this Page. Confirm the Page is linked to the Instagram account, that you manage it, and reconnect Instagram with Page permissions granted.',
                'debug_page_token_resolution' => [
                    'page_id_input' => $pageIdRaw,
                    'normalized_page_id' => $pageId,
                    'debug_accounts_status' => $resolved['accounts_status'] ?? null,
                    'debug_accounts_error' => $resolved['accounts_error'] ?? null,
                    'debug_accounts_count' => $resolved['accounts_count'] ?? null,
                    'debug_me_permissions' => $mePermissions,
                ],
            ], 422);
        }

        [$debugUser, $debugPage] = $this->debugTokens($userId, $userToken, $pageToken);

        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$igUserId.'/media', [
            'fields' => 'id,caption',
            'limit' => 1,
            'access_token' => $pageToken,
        ]);

        if (! $response->ok()) {
            $err = $response->json('error', []);
            $msg = is_array($err) ? ($err['message'] ?? 'Failed to load Instagram media.') : 'Failed to load Instagram media.';

            return response()->json([
                'message' => $msg,
                'error' => $err,
                'debug_me_permissions' => $mePermissions,
                'debug_token_user' => $debugUser ? ['scopes' => $debugUser['scopes'] ?? null, 'type' => $debugUser['type'] ?? null] : null,
                'debug_token_page' => $debugPage ? ['scopes' => $debugPage['scopes'] ?? null, 'type' => $debugPage['type'] ?? null] : null,
            ], min(499, max(400, $response->status())));
        }

        return ['message' => 'Instagram connection successful.', 'facebook_page_id' => $pageId, 'instagram_business_account_id' => $igUserId];
    }

    public function sync(Feed $feed): JsonResponse
    {
        $credential = $feed->socialCredential;

        if (! $credential || $credential->provider !== 'instagram') {
            return response()->json(['message' => 'No Instagram credential attached to this feed. Connect Instagram and assign it to the feed.'], 422);
        }

        $pageId = $this->normalizeFacebookPageId(trim((string) $feed->facebook_page_id));
        if ($pageId === '') {
            return response()->json(['message' => 'facebook_page_id (backing Page) is not set on this feed.'], 422);
        }

        $igUserId = trim((string) $feed->instagram_business_account_id);
        if ($igUserId === '') {
            return response()->json(['message' => 'instagram_business_account_id is not set on this feed.'], 422);
        }

        $pageToken = $this->resolveFacebookPageAccessToken($credential, $pageId);
        if (! $pageToken) {
            return response()->json(['message' => 'Could not access this Page. Confirm the Page is linked to the Instagram account, that you manage it, and reconnect Instagram with Page permissions granted.'], 422);
        }

        $userResp = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$igUserId, [
            'fields' => 'username,profile_picture_url',
            'access_token' => $pageToken,
        ]);
        if ($userResp->ok()) {
            $uname = trim((string) ($userResp->json('username') ?? ''));
            if ($uname !== '') {
                $feed->source_account_label = '@'.ltrim($uname, '@');
            }
            $pic = trim((string) ($userResp->json('profile_picture_url') ?? ''));
            if ($pic !== '') {
                $feed->account_avatar_url = $pic;
            }
            if ($feed->isDirty()) {
                $feed->save();
            }
        }

        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$igUserId.'/media', [
            'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
            'limit' => 25,
            'access_token' => $pageToken,
        ]);

        if (! $response->ok()) {
            $err = $response->json('error', []);
            $msg = is_array($err) ? ($err['message'] ?? 'Failed to load Instagram media.') : 'Failed to load Instagram media.';

            return response()->json(['message' => $msg, 'error' => $err], min(499, max(400, $response->status())));
        }

        $created = 0;
        foreach ($response->json('data', []) as $item) {
            $externalId = $item['id'] ?? null;
            if (! $externalId) {
                continue;
            }

            $caption = trim((string) ($item['caption'] ?? ''));
            $title = $caption !== '' ? mb_substr($caption, 0, 120) : 'Instagram media';
            $thumb = $this->thumbnail($item);
            $permalink = $item['permalink'] ?? null;
            $mediaUrl = $item['media_url'] ?? null;
            $link = is_string($permalink) && $permalink !== '' ? $permalink : (is_string($mediaUrl) ? $mediaUrl : null);

            PostSyncUpsert::apply($feed, (string) $externalId, [
                'title' => $title,
                'content' => $caption,
                'thumbnail_url' => $thumb,
                'video_url' => $link,
                'posted_at' => $item['timestamp'] ?? null,
            ]);
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json(['message' => 'Instagram sync complete', 'created' => $created, 'last_synced_at' => $feed->last_synced_at]);
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

    private function fetchMePermissions(?string $userToken): array
    {
        if (! $userToken) {
            return [];
        }

        $resp = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/permissions', [
            'access_token' => $userToken,
        ]);

        if (! $resp->ok()) {
            return [];
        }

        return array_values(array_map(fn ($p) => [
            'permission' => (string) ($p['permission'] ?? ''),
            'status' => (string) ($p['status'] ?? ''),
        ], $resp->json('data', [])));
    }

    private function debugTokens(int $userId, ?string $userToken, ?string $pageToken): array
    {
        $oauth = OAuthAppConfigResolver::resolveForUser($userId, 'facebook');

        if (! $oauth?->client_id || ! $oauth?->client_secret) {
            return [null, null];
        }

        $appToken = $oauth->client_id.'|'.$oauth->client_secret;
        $debugUser = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/debug_token', [
            'input_token' => $userToken, 'access_token' => $appToken,
        ])->json('data');
        $debugPage = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/debug_token', [
            'input_token' => $pageToken, 'access_token' => $appToken,
        ])->json('data');

        return [$debugUser, $debugPage];
    }
}
