<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;

class PublicFeedCache
{
    private const VERSION_TTL_SECONDS = 31_536_000; // ~1 year

    /** Bump when public feed JSON shape changes (e.g. proxied media URLs). */
    private const SCHEMA = 2;

    public static function version(string $publicKey): int
    {
        return (int) Cache::get(self::versionKey($publicKey), 1);
    }

    public static function cacheKey(string $publicKey, ?string $queryString): string
    {
        return 'public_feed:'.$publicKey.':s'.self::SCHEMA.':'.self::version($publicKey).':'.md5($queryString ?? '');
    }

    public static function bump(Workspace $workspace): void
    {
        $publicKey = trim((string) $workspace->public_key);
        if ($publicKey === '') {
            return;
        }

        $versionKey = self::versionKey($publicKey);
        $next = self::version($publicKey) + 1;
        Cache::put($versionKey, $next, self::VERSION_TTL_SECONDS);
    }

    private static function versionKey(string $publicKey): string
    {
        return 'public_feed_version:'.$publicKey;
    }
}
