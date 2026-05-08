<?php

namespace App\Support;

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

        return $post;
    }

    public static function apply(Feed $feed, string $externalId, array $contentAttributes): Post
    {
        return app(self::class)->upsert($feed, $externalId, $contentAttributes);
    }
}
