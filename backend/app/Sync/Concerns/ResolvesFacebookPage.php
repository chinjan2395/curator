<?php

namespace App\Sync\Concerns;

use App\Models\SocialCredential;
use App\Support\OAuthAppConfigResolver;
use Illuminate\Support\Facades\Http;

trait ResolvesFacebookPage
{
    private const FACEBOOK_GRAPH_VERSION = 'v23.0';

    /**
     * Pages from /me/accounts plus any Page IDs granted in OAuth granular_scopes
     * (newly assigned Business Manager pages often appear only in the latter).
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function listAccessibleFacebookPages(SocialCredential $credential, string $userToken): array
    {
        $byId = [];

        foreach ($this->fetchFacebookPagesFromMeAccounts($userToken) as $page) {
            $byId[$page['id']] = $page;
        }

        foreach ($this->pageIdsFromGranularScopes((int) $credential->user_id, $userToken) as $pageId) {
            if (isset($byId[$pageId])) {
                continue;
            }
            $meta = $this->fetchFacebookPageBrief($pageId, $userToken);
            if ($meta !== null) {
                $byId[$pageId] = $meta;
            }
        }

        $pages = array_values($byId);
        usort($pages, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $pages;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function fetchFacebookPagesFromMeAccounts(string $userToken): array
    {
        $pages = [];
        $nextUrl = 'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts';
        $query = [
            'fields' => 'id,name',
            'limit' => 100,
            'access_token' => $userToken,
        ];

        while ($nextUrl !== null) {
            $resp = $nextUrl === 'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts'
                ? Http::get($nextUrl, $query)
                : Http::get($nextUrl);

            if (! $resp->ok()) {
                break;
            }

            foreach ($resp->json('data', []) as $p) {
                $id = (string) ($p['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $pages[] = [
                    'id' => $id,
                    'name' => (string) ($p['name'] ?? ''),
                ];
            }

            $nextUrl = $resp->json('paging.next');
        }

        return $pages;
    }

    /**
     * Page IDs the user explicitly granted during Facebook Login (OAuth page picker).
     *
     * @return list<string>
     */
    protected function pageIdsFromGranularScopes(int $userId, string $userToken): array
    {
        $oauth = OAuthAppConfigResolver::resolveForUser($userId, 'facebook');
        if (! $oauth?->client_id || ! $oauth?->client_secret) {
            return [];
        }

        $appToken = $oauth->client_id.'|'.$oauth->client_secret;
        $data = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/debug_token', [
            'input_token' => $userToken,
            'access_token' => $appToken,
        ])->json('data');

        if (! is_array($data)) {
            return [];
        }

        $ids = [];
        foreach ($data['granular_scopes'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $scope = (string) ($entry['scope'] ?? '');
            if ($scope === '' || ! str_starts_with($scope, 'pages_')) {
                continue;
            }
            foreach ($entry['target_ids'] ?? [] as $targetId) {
                $id = (string) $targetId;
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @return array{id: string, name: string}|null
     */
    protected function fetchFacebookPageBrief(string $pageId, string $userToken): ?array
    {
        $resp = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId, [
            'fields' => 'id,name',
            'access_token' => $userToken,
        ]);

        if (! $resp->ok()) {
            return null;
        }

        $id = (string) ($resp->json('id') ?? '');
        if ($id === '') {
            return null;
        }

        return [
            'id' => $id,
            'name' => (string) ($resp->json('name') ?? ''),
        ];
    }

    protected function normalizeFacebookPageId(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('#[?&]id=(\d+)#', $raw, $m)) {
            return $m[1];
        }
        if (preg_match('~facebook\.com/(?:[^0-9]+/)?([0-9]{10,})~i', $raw, $m)) {
            return $m[1];
        }

        return $raw;
    }

    protected function resolveFacebookPageAccessToken(SocialCredential $credential, string $pageId): ?string
    {
        $userToken = $credential->access_token;
        if (! $userToken) {
            return null;
        }

        $nextUrl = 'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts';
        $query = [
            'fields' => 'id,access_token',
            'limit' => 100,
            'access_token' => $userToken,
        ];

        while ($nextUrl !== null) {
            $response = $nextUrl === 'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts'
                ? Http::get($nextUrl, $query)
                : Http::get($nextUrl);

            if ($response->ok()) {
                foreach ($response->json('data', []) as $page) {
                    if ((string) ($page['id'] ?? '') === (string) $pageId) {
                        $tok = $page['access_token'] ?? null;
                        if (is_string($tok) && $tok !== '') {
                            return $tok;
                        }
                    }
                }
            }

            $nextUrl = $response->ok() ? $response->json('paging.next') : null;
        }

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

        return null;
    }

    protected function resolveFacebookPageAccessTokenWithPerms(SocialCredential $credential, string $pageId): ?array
    {
        $userToken = $credential->access_token;
        if (! $userToken) {
            return null;
        }

        $accountsData = [];
        $accountsError = null;
        $accountsStatus = null;
        $nextUrl = 'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts';
        $query = [
            'fields' => 'id,access_token',
            'limit' => 100,
            'access_token' => $userToken,
        ];

        while ($nextUrl !== null) {
            $response = $nextUrl === 'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts'
                ? Http::get($nextUrl, $query)
                : Http::get($nextUrl);

            if (! $response->ok()) {
                $err = $response->json('error', []);
                $accountsError = is_array($err) ? $err : ['message' => 'Failed to load /me/accounts'];
                $accountsStatus = $response->status();
                break;
            }

            foreach ($response->json('data', []) as $page) {
                $accountsData[] = $page;
                if ((string) ($page['id'] ?? '') !== (string) $pageId) {
                    continue;
                }

                return [
                    'access_token' => $page['access_token'] ?? null,
                    'accounts_count' => count($accountsData),
                ];
            }

            $nextUrl = $response->json('paging.next');
        }

        $accountsCount = count($accountsData);

        $direct = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId, [
            'fields' => 'access_token',
            'access_token' => $userToken,
        ]);
        if ($direct->ok()) {
            $token = $direct->json('access_token');
            if (is_string($token) && $token !== '') {
                return [
                    'access_token' => $token,
                    'accounts_count' => $accountsCount,
                    'accounts_error' => $accountsError,
                    'accounts_status' => $accountsStatus,
                ];
            }
        }

        return [
            'access_token' => null,
            'accounts_count' => $accountsCount,
            'accounts_error' => $accountsError,
            'accounts_status' => $accountsStatus,
        ];
    }
}
