<?php

namespace App\Services\Media;

use App\Models\Post;
use App\Models\SocialCredential;
use App\Support\EphemeralMediaUrl;
use App\Sync\Concerns\ResolvesFacebookPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramMediaUrlRefresher
{
    use ResolvesFacebookPage;

    /**
     * Re-fetch a fresh media_url / thumbnail_url from Graph and update the post.
     */
    public function refresh(Post $post): bool
    {
        $feed = $post->feed;
        if (! $feed || $feed->type !== 'instagram') {
            return false;
        }

        $externalId = trim((string) $post->external_id);
        if ($externalId === '') {
            return false;
        }

        $credential = $feed->socialCredential;
        if (! $credential instanceof SocialCredential || $credential->provider !== 'instagram') {
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

        $response = Http::timeout(20)->get(
            'https://graph.facebook.com/'.self::FACEBOOK_GRAPH_VERSION.'/'.$externalId,
            [
                'fields' => 'media_type,media_url,thumbnail_url',
                'access_token' => $pageToken,
            ]
        );

        if (! $response->ok()) {
            Log::warning('Instagram media URL refresh failed.', [
                'post_id' => $post->id,
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);

            return false;
        }

        $item = $response->json();
        if (! is_array($item)) {
            return false;
        }

        $thumb = $this->thumbnailFromItem($item);
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
    private function thumbnailFromItem(array $item): ?string
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
}
