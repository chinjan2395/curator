<?php

namespace App\Services\Social\Support;

class MediaUrlClassifier
{
    /** @var list<string> */
    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'webm', 'mkv', 'm4v', 'wmv'];

    /** @var list<string> */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic', 'heif'];

    public static function isHttpsUrl(string $url): bool
    {
        return (bool) preg_match('#^https://#i', $url);
    }

    public static function extension(string $url): string
    {
        return strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    }

    public static function isVideo(string $url): bool
    {
        return in_array(self::extension($url), self::VIDEO_EXTENSIONS, true);
    }

    public static function isImage(string $url): bool
    {
        return self::looksLikeImageAsset($url);
    }

    public static function looksLikeImageAsset(string $url): bool
    {
        if (self::isVideo($url)) {
            return false;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp|heic|heif)(\?.*)?$/', $path);
    }

    public static function isVideoContentType(string $contentType): bool
    {
        return strtolower(trim($contentType)) === 'video';
    }

    /**
     * @param  list<string>  $urls
     */
    public static function assertNoVideos(array $urls, string $platformLabel): void
    {
        foreach ($urls as $url) {
            if (self::isVideo($url)) {
                throw new \RuntimeException(
                    "{$platformLabel} video upload is not yet supported. Attach image URLs instead."
                );
            }
        }
    }
}
