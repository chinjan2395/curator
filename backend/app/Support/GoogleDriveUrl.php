<?php

namespace App\Support;

class GoogleDriveUrl
{
    /**
     * Preview size is deliberately small for in-app <img> thumbnails.
     */
    public const PREVIEW_SIZE = 256;

    /**
     * Publishing the preview size ships a ~256px image that the platform then
     * upscales, so publishers request the full-resolution render instead.
     * Drive caps the result at the source resolution, making oversizing safe.
     */
    public const PUBLISH_SIZE = 2048;

    public static function isGoogleDriveUrl(?string $url): bool
    {
        return $url !== null && $url !== '' && str_contains($url, 'drive.google.com');
    }

    public static function extractFileId(string $url): ?string
    {
        if (preg_match('/[?&]id=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#/file/d/([^/]+)#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Thumbnail URLs embed more reliably in <img> tags than uc?export=media links.
     */
    public static function toThumbnailUrl(string $url, int $size = self::PREVIEW_SIZE): ?string
    {
        $fileId = self::extractFileId($url);

        return $fileId !== null
            ? 'https://drive.google.com/thumbnail?id='.$fileId.'&sz=w'.$size
            : null;
    }

    /**
     * Upgrade a stored Drive URL (including previously saved preview-size links)
     * to the full-resolution render used when handing media to a social API.
     */
    public static function toPublishUrl(string $url, int $size = self::PUBLISH_SIZE): ?string
    {
        return self::toThumbnailUrl($url, $size);
    }
}
