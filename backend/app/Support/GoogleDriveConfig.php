<?php

namespace App\Support;

class GoogleDriveConfig
{
    public static function isConfigured(): bool
    {
        $clientId = trim((string) config('filesystems.disks.googledrive.clientId', ''));
        $clientSecret = trim((string) config('filesystems.disks.googledrive.clientSecret', ''));
        $refreshToken = trim((string) config('filesystems.disks.googledrive.refreshToken', ''));

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return false;
        }

        // Access tokens (ya29.*) expire quickly and cannot be used as refresh tokens.
        if (str_starts_with($refreshToken, 'ya29.')) {
            return false;
        }

        return true;
    }
}
