<?php

namespace App\Support;

use App\Jobs\CachePostThumbnailJob;
use App\Models\Feed;
use App\Models\Post;
use App\Services\PublishService;

class PostSyncUpsert
{
    public function __construct(
        private readonly PublishService $publishService,
    ) {}

    /**
     * Upsert a post from provider sync: refresh content always; set status/published_at/pinned only on insert.
     */
    public function upsert(Feed $feed, string $externalId, array $contentAttributes): Post
    {
        $feed->loadMissing('workspace');

        $post = Post::query()->firstOrNew([
            'feed_id' => $feed->id,
            'external_id' => $externalId,
        ]);

        $isNew = ! $post->exists;
        $hadCachedThumb = filled($post->cached_thumbnail_path);

        $post->fill($contentAttributes);

        if ($isNew) {
            $post->pinned = false;

            if ($feed->auto_publish_new_posts) {
                $workspace = $feed->workspace;
                if ($workspace) {
                    $this->publishService->ensurePublicKey($workspace);
                }
                $post->status = 'approved';
                $post->published_at = now();
            } else {
                $post->status = 'pending';
                $post->published_at = null;
            }
        }

        $post->save();

        if ($isNew && $feed->auto_publish_new_posts && $feed->workspace) {
            PublicFeedCache::bump($feed->workspace);
        }

        // Cache ephemeral CDN thumbs once (or backfill), synchronously — the signed
        // CDN URL is guaranteed freshest right now, and production may not always
        // have a queue worker running to pick up a dispatched job in time.
        if (
            EphemeralMediaUrl::needsProxy($post->thumbnail_url)
            && (! $hadCachedThumb || ! filled($post->cached_thumbnail_path))
        ) {
            CachePostThumbnailJob::dispatchSync($post->id);
        }

        return $post;
    }

    public static function apply(Feed $feed, string $externalId, array $contentAttributes): Post
    {
        return app(self::class)->upsert($feed, $externalId, $contentAttributes);
    }
}
