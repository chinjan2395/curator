<?php

namespace App\Support;

/**
 * Detects third-party CDN URLs that expire (Instagram/Facebook signed links, etc.).
 */
class EphemeralMediaUrl
{
    /** @var list<string> */
    private const HOST_NEEDLES = [
        'cdninstagram.com',
        'fbcdn.net',
        'cdn.fbsbx.com',
        'scontent.',
        'instagram.com',
        'twimg.com',
        'tiktokcdn.com',
        'tiktokcdn-us.com',
        'muscdn.com',
    ];

    public static function needsProxy(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        foreach (self::HOST_NEEDLES as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return self::hasExpiryParam($url);
    }

    /**
     * Instagram/Facebook CDN signed URLs include oe= as a hex Unix expiry timestamp.
     */
    public static function isExpiredOrExpiringSoon(?string $url, int $skewSeconds = 120): bool
    {
        if ($url === null || trim($url) === '') {
            return true;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $params);
        $oe = $params['oe'] ?? null;
        if (! is_string($oe) || $oe === '' || ! ctype_xdigit($oe)) {
            return false;
        }

        $expiry = hexdec($oe);
        if ($expiry <= 0) {
            return false;
        }

        return $expiry <= (time() + $skewSeconds);
    }

    private static function hasExpiryParam(string $url): bool
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return false;
        }

        parse_str($query, $params);

        return isset($params['oe']) || isset($params['oh']);
    }
}
