<?php

namespace App\Support;

class GoogleDriveUrl
{
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
    public static function toThumbnailUrl(string $url, int $size = 256): ?string
    {
        $fileId = self::extractFileId($url);

        return $fileId !== null
            ? 'https://drive.google.com/thumbnail?id='.$fileId.'&sz=w'.$size
            : null;
    }
}
