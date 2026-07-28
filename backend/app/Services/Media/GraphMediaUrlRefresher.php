<?php

namespace App\Services\Media;

use App\Models\Feed;
use App\Models\Post;
use App\Models\SocialCredential;
use App\Support\EphemeralMediaUrl;
use App\Sync\Concerns\ResolvesFacebookPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Re-fetches a fresh media URL from the Facebook Graph API when a cached
 * thumbnail is missing and the stored signed CDN URL has already expired.
 * Supports both Instagram (media_url/thumbnail_url) and Facebook Page
 * (full_picture/attachments) post shapes.
 */
class GraphMediaUrlRefresher
{
    use ResolvesFacebookPage;

    /** @var list<string> */
    private const SUPPORTED_PROVIDERS = ['instagram', 'facebook'];

    public function refresh(Post $post): bool
    {
        $feed = $post->feed;
        if (! $feed || ! in_array($feed->type, self::SUPPORTED_PROVIDERS, true)) {
            return false;
        }

        $externalId = trim((string) $post->external_id);
        if ($externalId === '') {
            return false;
        }

        $credential = $feed->socialCredential;
        if (! $credential instanceof SocialCredential || $credential->provider !== $feed->type) {
            return false;
        }

        $pageId = $this->normalizeFacebookPageId(trim((string) $feed->facebook_page_id));
        if ($pageId === '') {
            return false;
        }

        $pageToken = $this->resolveFacebookPageAccessToken($credential, $pageId);
        if (! $pageToken) {
            return false;
        }

        $fields = $feed->type === 'instagram'
            ? 'media_type,media_url,thumbnail_url'
            : 'full_picture,attachments{media,subattachments}';

        $response = Http::timeout(20)->get(
            'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$externalId,
            [
                'fields' => $fields,
                'access_token' => $pageToken,
            ]
        );

        if (! $response->ok()) {
            Log::warning('Media URL refresh failed.', [
                'post_id' => $post->id,
                'provider' => $feed->type,
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);

            return false;
        }

        $item = $response->json();
        if (! is_array($item)) {
            return false;
        }

        $thumb = $feed->type === 'instagram'
            ? $this->thumbnailFromInstagramItem($item)
            : $this->thumbnailFromFacebookPost($item);

        if ($thumb === null) {
            return false;
        }

        $post->thumbnail_url = $thumb;
        $raw = is_array($post->raw_data) ? $post->raw_data : [];
        $raw['media'] = array_merge(is_array($raw['media'] ?? null) ? $raw['media'] : [], $item);
        $post->raw_data = $raw;
        $post->save();

        return true;
    }

    /**
     * Re-fetches the account/page profile picture from Graph when the stored
     * `account_avatar_url` has expired (or was never cached). Mirrors what the
     * Instagram/Facebook syncers do, so a request can self-heal between syncs
     * (e.g. after the local media cache disk was wiped on redeploy).
     */
    public function refreshFeedAvatar(Feed $feed): bool
    {
        if (! in_array($feed->type, self::SUPPORTED_PROVIDERS, true)) {
            return false;
        }

        $credential = $feed->socialCredential;
        if (! $credential instanceof SocialCredential || $credential->provider !== $feed->type) {
            return false;
        }

        $pageId = $this->normalizeFacebookPageId(trim((string) $feed->facebook_page_id));
        if ($pageId === '') {
            return false;
        }

        $pageToken = $this->resolveFacebookPageAccessToken($credential, $pageId);
        if (! $pageToken) {
            return false;
        }

        if ($feed->type === 'instagram') {
            $igUserId = trim((string) $feed->instagram_business_account_id);
            if ($igUserId === '') {
                return false;
            }

            $response = Http::timeout(20)->get(
                'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$igUserId,
                ['fields' => 'profile_picture_url', 'access_token' => $pageToken]
            );
            $pic = trim((string) ($response->ok() ? $response->json('profile_picture_url') : ''));
        } else {
            $response = Http::timeout(20)->get(
                'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$pageId,
                ['fields' => 'picture.type(large){url}', 'access_token' => $pageToken]
            );
            $pic = trim((string) ($response->ok() ? $response->json('picture.data.url') : ''));
        }

        if ($pic === '') {
            Log::warning('Feed avatar URL refresh failed.', [
                'feed_id' => $feed->id,
                'provider' => $feed->type,
                'status' => $response->status(),
            ]);

            return false;
        }

        $feed->account_avatar_url = $pic;
        $feed->save();

        return true;
    }

    /** @param  array<string, mixed>  $item */
    private function thumbnailFromInstagramItem(array $item): ?string
    {
        if (! empty($item['thumbnail_url']) && is_string($item['thumbnail_url'])) {
            return $item['thumbnail_url'];
        }

        if (($item['media_type'] ?? '') === 'IMAGE' && ! empty($item['media_url']) && is_string($item['media_url'])) {
            return $item['media_url'];
        }

        if (! empty($item['media_url']) && is_string($item['media_url']) && ! EphemeralMediaUrl::isExpiredOrExpiringSoon($item['media_url'])) {
            return $item['media_url'];
        }

        return null;
    }

    /** @param  array<string, mixed>  $post */
    private function thumbnailFromFacebookPost(array $post): ?string
    {
        if (! empty($post['full_picture']) && is_string($post['full_picture'])) {
            return $post['full_picture'];
        }

        foreach ($post['attachments']['data'] ?? [] as $att) {
            $src = $att['media']['image']['src'] ?? null;
            if (is_string($src) && $src !== '') {
                return $src;
            }
            foreach ($att['subattachments']['data'] ?? [] as $sub) {
                $subSrc = $sub['media']['image']['src'] ?? null;
                if (is_string($subSrc) && $subSrc !== '') {
                    return $subSrc;
                }
            }
        }

        return null;
    }
}
