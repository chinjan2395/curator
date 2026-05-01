<?php

namespace App\Support;

use App\Models\Feed;

class FeedAccountDisplay
{
    /** Canonical YouTube channel IDs are 24 chars starting with UC — not visitor-facing. */
    private static function isYoutubeOpaqueChannelId(string $s): bool
    {
        return (bool) preg_match('/^UC[\w-]{22}$/', $s);
    }

    /** Display handle / channel / page name for embeds when source_account_label is empty. */
    public static function fallback(?Feed $feed): ?string
    {
        if (! $feed) {
            return null;
        }

        $u = trim((string) ($feed->twitter_username ?? ''));
        if ($u !== '') {
            return '@'.ltrim($u, '@');
        }

        if ($feed->type === 'youtube') {
            $raw = trim((string) ($feed->youtube_channel_id ?? ''));
            // Handles (@Name or slug) are public; never show opaque UC… IDs here.
            if ($raw !== '' && ! self::isYoutubeOpaqueChannelId($raw)) {
                return str_starts_with($raw, '@') ? $raw : '@'.$raw;
            }
        }

        $n = trim((string) ($feed->name ?? ''));

        return $n !== '' ? $n : null;
    }

    public static function resolve(?Feed $feed): ?string
    {
        if (! $feed) {
            return null;
        }

        $label = trim((string) ($feed->source_account_label ?? ''));
        if ($label !== '') {
            return $label;
        }

        return self::fallback($feed);
    }
}
