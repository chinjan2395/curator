<?php

namespace App\Services\Media;

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
