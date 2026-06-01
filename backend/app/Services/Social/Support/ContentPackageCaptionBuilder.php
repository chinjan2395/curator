<?php

namespace App\Services\Social\Support;

use App\Models\ContentPackage;

class ContentPackageCaptionBuilder
{
    public static function build(ContentPackage $package, ?int $maxLength = null): string
    {
        $caption = trim((string) $package->caption);
        $hashtags = $package->hashtags ?? [];

        if (is_array($hashtags) && $hashtags !== []) {
            $tagLine = implode(' ', array_map(static function ($tag) {
                $tag = trim((string) $tag);

                return str_starts_with($tag, '#') ? $tag : '#'.$tag;
            }, $hashtags));
            $caption = trim($caption.' '.$tagLine);
        }

        if ($caption === '' || $maxLength === null) {
            return $caption;
        }

        if (mb_strlen($caption) > $maxLength) {
            return mb_substr($caption, 0, $maxLength - 1).'…';
        }

        return $caption;
    }

    /** @return list<string> */
    public static function mediaUrls(ContentPackage $package): array
    {
        $urls = $package->media_urls ?? [];

        return is_array($urls) ? array_values(array_filter($urls, static fn ($u) => is_string($u) && $u !== '')) : [];
    }

    public static function firstMediaUrl(ContentPackage $package): ?string
    {
        $urls = self::mediaUrls($package);

        return $urls[0] ?? null;
    }
}
