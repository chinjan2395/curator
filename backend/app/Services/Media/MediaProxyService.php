<?php

namespace App\Services\Media;

use App\Models\Feed;
use App\Models\Post;
use App\Support\EphemeralMediaUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaProxyService
{
    private const DISK = 'local';

    private const MAX_BYTES = 15_000_000;

    public function __construct(
        private readonly GraphMediaUrlRefresher $graphRefresher,
    ) {}

    public function streamPostThumbnail(Post $post): StreamedResponse
    {
        if (! $this->ensurePostThumbnailCached($post)) {
            abort(404, 'Thumbnail unavailable.');
        }

        $post->refresh();

        return $this->streamFromDisk(
            (string) $post->cached_thumbnail_disk,
            (string) $post->cached_thumbnail_path,
            (string) ($post->cached_thumbnail_mime ?: 'image/jpeg'),
        );
    }

    public function streamFeedAvatar(Feed $feed): StreamedResponse
    {
        if (! $this->ensureFeedAvatarCached($feed)) {
            abort(404, 'Avatar unavailable.');
        }

        $feed->refresh();

        return $this->streamFromDisk(
            (string) $feed->cached_avatar_disk,
            (string) $feed->cached_avatar_path,
            (string) ($feed->cached_avatar_mime ?: 'image/jpeg'),
        );
    }

    public function ensurePostThumbnailCached(Post $post): bool
    {
        if ($this->postCacheExists($post)) {
            return true;
        }

        $source = trim((string) $post->thumbnail_url);
        if ($source === '') {
            Log::warning('Media proxy: post has no thumbnail_url to cache.', ['post_id' => $post->id]);

            return false;
        }

        if (EphemeralMediaUrl::isExpiredOrExpiringSoon($source)) {
            if ($this->graphRefresher->refresh($post)) {
                $post->refresh();
                $source = trim((string) $post->thumbnail_url);
            } else {
                Log::info('Media proxy: source URL expired and Graph refresh failed.', [
                    'post_id' => $post->id,
                    'feed_id' => $post->feed_id,
                ]);
            }
        }

        if ($source !== '' && $this->downloadPostThumbnail($post, $source)) {
            return true;
        }

        // Last resort: refresh Graph URL then retry download.
        if ($this->graphRefresher->refresh($post)) {
            $post->refresh();
            $fresh = trim((string) $post->thumbnail_url);
            if ($fresh !== '' && $this->downloadPostThumbnail($post, $fresh)) {
                return true;
            }
        }

        if (! $this->postCacheExists($post)) {
            Log::warning('Media proxy: unable to cache post thumbnail after refresh + download attempts.', [
                'post_id' => $post->id,
                'feed_id' => $post->feed_id,
            ]);
        }

        return $this->postCacheExists($post);
    }

    public function ensureFeedAvatarCached(Feed $feed): bool
    {
        if ($this->feedCacheExists($feed)) {
            return true;
        }

        $source = trim((string) $feed->account_avatar_url);

        if ($source !== '' && EphemeralMediaUrl::isExpiredOrExpiringSoon($source)) {
            if ($this->graphRefresher->refreshFeedAvatar($feed)) {
                $feed->refresh();
                $source = trim((string) $feed->account_avatar_url);
            } else {
                Log::info('Media proxy: feed avatar URL expired and Graph refresh failed.', ['feed_id' => $feed->id]);
            }
        }

        if ($source !== '' && $this->downloadFeedAvatar($feed, $source)) {
            return true;
        }

        // Last resort: the source URL may already be dead (403/404) without
        // an `oe=` expiry we could detect up front — try a Graph refresh + retry.
        if ($this->graphRefresher->refreshFeedAvatar($feed)) {
            $feed->refresh();
            $fresh = trim((string) $feed->account_avatar_url);
            if ($fresh !== '' && $this->downloadFeedAvatar($feed, $fresh)) {
                return true;
            }
        }

        if (! $this->feedCacheExists($feed)) {
            Log::warning('Media proxy: unable to cache feed avatar after refresh + download attempts.', ['feed_id' => $feed->id]);
        }

        return $this->feedCacheExists($feed);
    }

    /**
     * True when the feed has no usable cached avatar file on disk — used by
     * syncers to decide whether to (re)dispatch caching, since the DB column
     * being set doesn't guarantee the underlying file still exists (e.g. an
     * ephemeral filesystem wiped it on redeploy/restart).
     */
    public function feedAvatarCacheMissing(Feed $feed): bool
    {
        return ! $this->feedCacheExists($feed);
    }

    public function clearPostThumbnailCache(Post $post): void
    {
        if ($post->cached_thumbnail_path && $post->cached_thumbnail_disk) {
            try {
                Storage::disk($post->cached_thumbnail_disk)->delete($post->cached_thumbnail_path);
            } catch (\Throwable) {
                // ignore
            }
        }

        $post->forceFill([
            'cached_thumbnail_path' => null,
            'cached_thumbnail_disk' => null,
            'cached_thumbnail_mime' => null,
        ])->save();
    }

    private function downloadPostThumbnail(Post $post, string $sourceUrl): bool
    {
        $fetched = $this->fetchBinary($sourceUrl);
        if ($fetched === null) {
            return false;
        }

        $path = 'media-cache/posts/'.$post->id.'/thumbnail';
        Storage::disk(self::DISK)->put($path, $fetched['body']);

        $post->forceFill([
            'cached_thumbnail_path' => $path,
            'cached_thumbnail_disk' => self::DISK,
            'cached_thumbnail_mime' => $fetched['mime'],
        ])->save();

        return true;
    }

    private function downloadFeedAvatar(Feed $feed, string $sourceUrl): bool
    {
        $fetched = $this->fetchBinary($sourceUrl);
        if ($fetched === null) {
            return false;
        }

        $path = 'media-cache/feeds/'.$feed->id.'/avatar';
        Storage::disk(self::DISK)->put($path, $fetched['body']);

        $feed->forceFill([
            'cached_avatar_path' => $path,
            'cached_avatar_disk' => self::DISK,
            'cached_avatar_mime' => $fetched['mime'],
        ])->save();

        return true;
    }

    /** @return array{body: string, mime: string}|null */
    private function fetchBinary(string $url): ?array
    {
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'CuratorMediaProxy/1.0',
                    'Accept' => 'image/*,*/*',
                ])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Media proxy fetch failed.', [
                'url_host' => parse_url($url, PHP_URL_HOST),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::info('Media proxy fetch returned non-success status.', [
                'url_host' => parse_url($url, PHP_URL_HOST),
                'status' => $response->status(),
            ]);

            return null;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            Log::info('Media proxy fetch returned empty or oversized body.', [
                'url_host' => parse_url($url, PHP_URL_HOST),
                'size' => strlen($body),
            ]);

            return null;
        }

        $mime = (string) ($response->header('Content-Type') ?: 'image/jpeg');
        $mime = trim(explode(';', $mime)[0]);
        if ($mime === '' || str_starts_with($mime, 'text/')) {
            $mime = 'image/jpeg';
        }

        return ['body' => $body, 'mime' => $mime];
    }

    private function postCacheExists(Post $post): bool
    {
        if (! $post->cached_thumbnail_path || ! $post->cached_thumbnail_disk) {
            return false;
        }

        try {
            return Storage::disk($post->cached_thumbnail_disk)->exists($post->cached_thumbnail_path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function feedCacheExists(Feed $feed): bool
    {
        if (! $feed->cached_avatar_path || ! $feed->cached_avatar_disk) {
            return false;
        }

        try {
            return Storage::disk($feed->cached_avatar_disk)->exists($feed->cached_avatar_path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function streamFromDisk(string $disk, string $path, string $mime): StreamedResponse
    {
        $storage = Storage::disk($disk);
        $stream = $storage->readStream($path);

        return response()->stream(function () use ($stream) {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
