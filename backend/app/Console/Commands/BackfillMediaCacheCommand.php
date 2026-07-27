<?php

namespace App\Console\Commands;

use App\Models\Feed;
use App\Models\Post;
use App\Services\Media\MediaProxyService;
use App\Support\EphemeralMediaUrl;
use Illuminate\Console\Command;

class BackfillMediaCacheCommand extends Command
{
    protected $signature = 'media:backfill-cache {--limit=300 : Max posts/feeds to attempt per run}';

    protected $description = 'Cache Instagram/Facebook (etc.) thumbnails and avatars that are missing a local cached copy, refreshing expired Graph URLs where possible.';

    public function handle(MediaProxyService $mediaProxy): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $posts = Post::query()
            ->whereNotNull('thumbnail_url')
            ->where(function ($q) {
                $q->whereNull('cached_thumbnail_path')->orWhere('cached_thumbnail_path', '');
            })
            ->orderByDesc('posted_at')
            ->limit($limit)
            ->get();

        $postsCached = 0;
        $postsFailed = 0;

        foreach ($posts as $post) {
            if (! EphemeralMediaUrl::needsProxy($post->thumbnail_url)) {
                continue;
            }

            if ($mediaProxy->ensurePostThumbnailCached($post)) {
                $postsCached++;
            } else {
                $postsFailed++;
            }
        }

        $feeds = Feed::query()
            ->whereNotNull('account_avatar_url')
            ->where(function ($q) {
                $q->whereNull('cached_avatar_path')->orWhere('cached_avatar_path', '');
            })
            ->limit($limit)
            ->get();

        $feedsCached = 0;
        $feedsFailed = 0;

        foreach ($feeds as $feed) {
            if (! EphemeralMediaUrl::needsProxy($feed->account_avatar_url)) {
                continue;
            }

            if ($mediaProxy->ensureFeedAvatarCached($feed)) {
                $feedsCached++;
            } else {
                $feedsFailed++;
            }
        }

        $this->info("Post thumbnails: {$postsCached} cached, {$postsFailed} failed.");
        $this->info("Feed avatars: {$feedsCached} cached, {$feedsFailed} failed.");

        if ($postsFailed > 0 || $feedsFailed > 0) {
            $this->warn('Failures usually mean the feed\'s social credential is disconnected/expired, or the original post is old enough that Graph no longer has metadata for it. Check storage/logs/laravel.log for "Media proxy" entries.');
        }

        return self::SUCCESS;
    }
}
