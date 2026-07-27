<?php

namespace App\Support;

use App\Models\Feed;
use App\Models\Post;

/**
 * Rewrites ephemeral third-party CDN URLs to durable Curator proxy endpoints.
 */
class ProxiedMediaUrl
{
    public static function forPost(?Post $post): ?string
    {
        if (! $post || ! $post->thumbnail_url) {
            return null;
        }

        if (! EphemeralMediaUrl::needsProxy((string) $post->thumbnail_url)) {
            return (string) $post->thumbnail_url;
        }

        return route('media.posts.thumbnail', ['post' => $post->id], absolute: true);
    }

    public static function forPostId(int $postId, ?string $thumbnailUrl): ?string
    {
        if ($thumbnailUrl === null || trim($thumbnailUrl) === '') {
            return null;
        }

        if (! EphemeralMediaUrl::needsProxy($thumbnailUrl)) {
            return $thumbnailUrl;
        }

        return route('media.posts.thumbnail', ['post' => $postId], absolute: true);
    }

    public static function forFeedAvatar(?Feed $feed): ?string
    {
        if (! $feed || ! $feed->account_avatar_url) {
            return null;
        }

        if (! EphemeralMediaUrl::needsProxy((string) $feed->account_avatar_url)) {
            return (string) $feed->account_avatar_url;
        }

        return route('media.feeds.avatar', ['feed' => $feed->id], absolute: true);
    }
}
