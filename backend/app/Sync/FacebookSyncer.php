<?php

namespace App\Sync;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use App\Support\OAuthAppConfigResolver;
use App\Sync\Concerns\ResolvesFacebookPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class FacebookSyncer
{
    use ResolvesFacebookPage;

    public function pages(SocialCredential $credential): array|JsonResponse
    {
        $userToken = $credential->access_token;
        if (! $userToken) {
            return response()->json(['message' => 'Facebook credential token missing. Reconnect Facebook.'], 422);
        }

        $resp = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts', [
            'fields' => 'id,name',
            'limit' => 100,
            'access_token' => $userToken,
        ]);

        if (! $resp->ok()) {
            $err = $resp->json('error', []);
            $msg = is_array($err) ? ($err['message'] ?? 'Failed to load accessible Pages.') : 'Failed to load accessible Pages.';

            return response()->json(['message' => $msg, 'error' => $err], min(499, max(400, $resp->status())));
        }

        $pages = array_values(array_filter(array_map(fn ($p) => [
            'id' => (string) ($p['id'] ?? ''),
            'name' => (string) ($p['name'] ?? ''),
        ], $resp->json('data', [])), fn ($p) => $p['id'] !== ''));

        return ['pages' => $pages];
    }

    public function test(SocialCredential $credential, string $pageIdRaw, int $userId): array|JsonResponse
    {
        $pageId = $this->normalizeFacebookPageId($pageIdRaw);
        if ($pageId === '') {
            return response()->json(['message' => 'Enter a Facebook Page ID.'], 422);
        }

        $userToken = $credential->access_token;
        $mePermissions = $this->fetchMePermissions($userToken);

        $resolved = $this->resolveFacebookPageAccessTokenWithPerms($credential, $pageId) ?? [];
        $pageToken = $resolved['access_token'] ?? null;
        if (! $pageToken) {
            return response()->json([
                'message' => 'Could not access this Page. Confirm the Page ID, that you manage the Page, and reconnect Facebook with Page permissions granted.',
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

        return ['message' => 'Facebook connection successful.', 'page_id' => $pageId];
    }

    public function sync(Feed $feed): JsonResponse
    {
        $credential = $feed->socialCredential;

        if (! $credential || $credential->provider !== 'facebook') {
            return response()->json(['message' => 'No Facebook credential attached to this feed. Connect Facebook and assign it to the feed.'], 422);
        }

        $pageId = $this->normalizeFacebookPageId(trim((string) $feed->facebook_page_id));
        if ($pageId === '') {
            return response()->json(['message' => 'facebook_page_id is not set on this feed.'], 422);
        }

        $pageToken = $this->resolveFacebookPageAccessToken($credential, $pageId);
        if (! $pageToken) {
            return response()->json(['message' => 'Could not access this Page. Confirm the Page ID, that you manage the Page, and reconnect Facebook with Page permissions granted.'], 422);
        }

        $pageMeta = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId, [
            'fields' => 'name,picture.type(large){url}',
            'access_token' => $pageToken,
        ]);
        if ($pageMeta->ok()) {
            $pageName = trim((string) ($pageMeta->json('name') ?? ''));
            if ($pageName !== '') {
                $feed->source_account_label = $pageName;
            }
            $pic = trim((string) ($pageMeta->json('picture.data.url') ?? ''));
            if ($pic !== '') {
                $feed->account_avatar_url = $pic;
            }
            if ($feed->isDirty()) {
                $feed->save();
            }
        }

        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId.'/feed', [
            'fields' => 'id,message,story,created_time,permalink_url,full_picture,attachments{media,subattachments}',
            'limit' => 25,
            'access_token' => $pageToken,
        ]);

        if (! $response->ok()) {
            $err = $response->json('error', []);
            $msg = is_array($err) ? ($err['message'] ?? 'Failed to load Facebook Page feed.') : 'Failed to load Facebook Page feed.';

            return response()->json(['message' => $msg, 'error' => $err], min(499, max(400, $response->status())));
        }

        $created = 0;
        foreach ($response->json('data', []) as $post) {
            $externalId = $post['id'] ?? null;
            if (! $externalId) {
                continue;
            }

            $message = trim((string) ($post['message'] ?? ''));
            $story = trim((string) ($post['story'] ?? ''));
            $body = $message !== '' ? $message : $story;
            $title = $body !== '' ? mb_substr($body, 0, 120) : 'Facebook post';

            Post::updateOrCreate(
                ['feed_id' => $feed->id, 'external_id' => $externalId],
                [
                    'title' => $title,
                    'content' => $body,
                    'thumbnail_url' => $this->thumbnail($post),
                    'video_url' => $post['permalink_url'] ?? null,
                    'posted_at' => $post['created_time'] ?? null,
                    'status' => 'pending',
                    'pinned' => false,
                ]
            );
            $created++;
        }

        $feed->update(['last_synced_at' => now()]);

        return response()->json(['message' => 'Facebook sync complete', 'created' => $created, 'last_synced_at' => $feed->last_synced_at]);
    }

    private function thumbnail(array $post): ?string
    {
        if (! empty($post['full_picture'])) {
            return $post['full_picture'];
        }

        foreach ($post['attachments']['data'] ?? [] as $att) {
            if ($src = $att['media']['image']['src'] ?? null) {
                return $src;
            }
            foreach ($att['subattachments']['data'] ?? [] as $sub) {
                if ($subSrc = $sub['media']['image']['src'] ?? null) {
                    return $subSrc;
                }
            }
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
