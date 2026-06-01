<?php

namespace App\Services\Social\Support;

use App\Models\Feed;
use App\Models\SocialCredential;
use App\Sync\Concerns\ResolvesFacebookPage;
use RuntimeException;

class FacebookGraphClient
{
    use ResolvesFacebookPage;

    public function pageTokenForCredential(SocialCredential $credential, ?string $pageId = null): array
    {
        $userToken = $credential->access_token;
        if (! $userToken) {
            throw new RuntimeException('Facebook access token missing. Reconnect Facebook in Credentials.');
        }

        $pageId = $pageId ?: $this->resolvePageId($credential, $userToken);
        $pageToken = $this->resolveFacebookPageAccessToken($credential, $pageId);
        if (! $pageToken) {
            throw new RuntimeException('Could not resolve a Page access token. Link a Facebook Page feed or reconnect with pages_manage_posts.');
        }

        return ['page_id' => $pageId, 'page_token' => $pageToken];
    }

    public function instagramContext(SocialCredential $credential): array
    {
        $feed = Feed::query()
            ->where('social_credential_id', $credential->id)
            ->whereNotNull('instagram_business_account_id')
            ->where('instagram_business_account_id', '!=', '')
            ->first();

        if ($feed?->instagram_business_account_id && $feed->facebook_page_id) {
            $resolved = $this->pageTokenForCredential($credential, (string) $feed->facebook_page_id);

            return [
                'ig_user_id' => (string) $feed->instagram_business_account_id,
                'page_id' => $resolved['page_id'],
                'page_token' => $resolved['page_token'],
            ];
        }

        $userToken = $credential->access_token;
        if (! $userToken) {
            throw new RuntimeException('Instagram access token missing. Reconnect Instagram.');
        }

        $resp = \Illuminate\Support\Facades\Http::get('https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/me/accounts', [
            'fields' => 'id,instagram_business_account{id}',
            'limit' => 25,
            'access_token' => $userToken,
        ]);

        if (! $resp->ok()) {
            throw new RuntimeException('Failed to list Facebook Pages for Instagram publishing.');
        }

        foreach ($resp->json('data', []) as $row) {
            $igId = (string) ($row['instagram_business_account']['id'] ?? '');
            $pageId = (string) ($row['id'] ?? '');
            if ($igId === '' || $pageId === '') {
                continue;
            }

            $resolved = $this->pageTokenForCredential($credential, $pageId);

            return [
                'ig_user_id' => $igId,
                'page_id' => $resolved['page_id'],
                'page_token' => $resolved['page_token'],
            ];
        }

        throw new RuntimeException('No Instagram Business account linked to a Facebook Page. Configure an Instagram feed first.');
    }

    private function resolvePageId(SocialCredential $credential, string $userToken): string
    {
        $fromFeed = Feed::query()
            ->where('social_credential_id', $credential->id)
            ->whereNotNull('facebook_page_id')
            ->where('facebook_page_id', '!=', '')
            ->value('facebook_page_id');

        if (is_string($fromFeed) && $fromFeed !== '') {
            return $fromFeed;
        }

        $pages = $this->listAccessibleFacebookPages($credential, $userToken);
        if ($pages === []) {
            throw new RuntimeException('No Facebook Pages available for publishing.');
        }

        return (string) $pages[0]['id'];
    }
}
