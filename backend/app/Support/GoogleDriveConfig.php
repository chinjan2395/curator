<?php

namespace App\Support;

class GoogleDriveConfig
{
    /**
     * Resolve Google Drive credentials from Laravel config, falling back to process
     * environment variables. On hosts like Render, config:cache during build can
     * bake empty values while runtime env vars are set on the web service only.
     *
     * @return array{
     *     clientId: string,
     *     clientSecret: string,
     *     refreshToken: string,
     *     folder: string,
     *     teamDriveId: ?string,
     *     sharedFolderId: ?string
     * }
     */
    public static function resolve(): array
    {
        return [
            'clientId' => self::resolveValue('clientId', 'GOOGLE_DRIVE_CLIENT_ID'),
            'clientSecret' => self::resolveValue('clientSecret', 'GOOGLE_DRIVE_CLIENT_SECRET'),
            'refreshToken' => self::resolveValue('refreshToken', 'GOOGLE_DRIVE_REFRESH_TOKEN'),
            'folder' => self::resolveValue('folder', 'GOOGLE_DRIVE_FOLDER', '/'),
            'teamDriveId' => self::resolveOptional('teamDriveId', 'GOOGLE_DRIVE_TEAM_DRIVE_ID'),
            'sharedFolderId' => self::resolveOptional('sharedFolderId', 'GOOGLE_DRIVE_SHARED_FOLDER_ID'),
        ];
    }

    public static function isConfigured(): bool
    {
        $credentials = self::resolve();
        $clientId = $credentials['clientId'];
        $clientSecret = $credentials['clientSecret'];
        $refreshToken = $credentials['refreshToken'];

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return false;
        }

        // Access tokens (ya29.*) expire quickly and cannot be used as refresh tokens.
        if (str_starts_with($refreshToken, 'ya29.')) {
            return false;
        }

        return true;
    }

    private static function resolveValue(string $configKey, string $envKey, string $default = ''): string
    {
        $fromConfig = trim((string) config("filesystems.disks.googledrive.{$configKey}", ''));
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        $fromEnv = getenv($envKey);
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        return $default;
    }

    private static function resolveOptional(string $configKey, string $envKey): ?string
    {
        $value = self::resolveValue($configKey, $envKey);

        return $value !== '' ? $value : null;
    }
}
