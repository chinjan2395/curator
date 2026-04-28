<?php

namespace App\Sync\Concerns;

use App\Models\SocialCredential;
use Illuminate\Support\Facades\Http;

trait ResolvesFacebookPage
{
    private const FACEBOOK_GRAPH_VERSION = 'v23.0';

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

        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts', [
            'fields' => 'id,access_token',
            'access_token' => $userToken,
        ]);

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

        $response = Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts', [
            'fields' => 'id,access_token',
            'access_token' => $userToken,
        ]);

        $accountsData = $response->ok() ? $response->json('data', []) : [];
        $accountsCount = is_array($accountsData) ? count($accountsData) : 0;
        $accountsError = null;
        if (! $response->ok()) {
            $err = $response->json('error', []);
            $accountsError = is_array($err) ? $err : ['message' => 'Failed to load /me/accounts'];
        }

        if ($response->ok()) {
            foreach ($accountsData as $page) {
                if ((string) ($page['id'] ?? '') !== (string) $pageId) {
                    continue;
                }

                return [
                    'access_token' => $page['access_token'] ?? null,
                    'accounts_count' => $accountsCount,
                ];
            }
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
                    'accounts_count' => $accountsCount,
                    'accounts_error' => $accountsError,
                    'accounts_status' => $response->status(),
                ];
            }
        }

        return [
            'access_token' => null,
            'accounts_count' => $accountsCount,
            'accounts_error' => $accountsError,
            'accounts_status' => $response->status(),
        ];
    }
}
