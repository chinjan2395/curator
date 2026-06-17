<?php

namespace App\Support;

use App\Models\GoogleDriveConnection;
use App\Models\OAuthAppConfig;
use App\Support\OAuthAppConfigResolver;

class GoogleDriveConfig
{
    /**
     * Resolve Google Drive credentials from the in-app connection, Laravel config,
     * or process environment variables.
     *
     * @return array{
     *     clientId: string,
     *     clientSecret: string,
     *     refreshToken: string,
     *     folder: string,
     *     teamDriveId: ?string,
     *     sharedFolderId: ?string,
     *     source: 'database'|'env'|null
     * }
     */
    public static function resolve(): array
    {
        $connection = GoogleDriveConnection::current();
        $refreshToken = self::normalizeRefreshToken($connection?->refresh_token ?? '');
        $source = null;

        if ($refreshToken !== '') {
            $source = 'database';
        } else {
            $refreshToken = self::normalizeRefreshToken(
                self::resolveValue('refreshToken', 'GOOGLE_DRIVE_REFRESH_TOKEN')
            );
            if ($refreshToken !== '') {
                $source = 'env';
            }
        }

        return [
            'clientId' => self::resolveClientId(),
            'clientSecret' => self::resolveClientSecret(),
            'refreshToken' => $refreshToken,
            'folder' => self::resolveValue('folder', 'GOOGLE_DRIVE_FOLDER', '/'),
            'teamDriveId' => self::resolveOptional('teamDriveId', 'GOOGLE_DRIVE_TEAM_DRIVE_ID'),
            'sharedFolderId' => self::resolveOptional('sharedFolderId', 'GOOGLE_DRIVE_SHARED_FOLDER_ID'),
            'source' => $source,
        ];
    }

    public static function resolveClientId(): string
    {
        $fromDisk = self::resolveValue('clientId', 'GOOGLE_DRIVE_CLIENT_ID');
        if ($fromDisk !== '') {
            return $fromDisk;
        }

        return trim((string) config('services.google.client_id', ''));
    }

    public static function resolveClientSecret(): string
    {
        $fromDisk = self::resolveValue('clientSecret', 'GOOGLE_DRIVE_CLIENT_SECRET');
        if ($fromDisk !== '') {
            return $fromDisk;
        }

        return trim((string) config('services.google.client_secret', ''));
    }

    public static function isConfigured(): bool
    {
        $credentials = self::resolve();

        return self::isValidRefreshToken($credentials['refreshToken'])
            && $credentials['clientId'] !== ''
            && $credentials['clientSecret'] !== '';
    }

    public static function status(?int $userId = null): array
    {
        $oauthReady = $userId !== null
            ? self::oauthReadyForUser($userId)
            : self::oauthReady();

        $connection = GoogleDriveConnection::current();

        if ($connection && self::isValidRefreshToken($connection->refresh_token ?? '')) {
            return array_merge($connection->toStatusArray(), ['oauth_ready' => $oauthReady]);
        }

        if ($connection && $connection->token_health === 'needs_reauth') {
            return [
                'connected' => true,
                'account_email' => $connection->account_email,
                'account_label' => $connection->account_label,
                'token_health' => $connection->token_health,
                'last_error' => $connection->last_error,
                'connected_at' => $connection->connected_at?->toIso8601String(),
                'expires_at' => $connection->expires_at?->toIso8601String(),
                'source' => 'database',
                'oauth_ready' => $oauthReady,
            ];
        }

        $credentials = self::resolve();

        if (self::isConfigured() && ($credentials['source'] ?? null) === 'env') {
            return [
                'connected' => true,
                'account_email' => null,
                'account_label' => 'Environment configuration',
                'token_health' => 'valid',
                'last_error' => null,
                'connected_at' => null,
                'expires_at' => null,
                'source' => 'env',
                'oauth_ready' => $oauthReady,
            ];
        }

        return [
            'connected' => false,
            'account_email' => null,
            'account_label' => null,
            'token_health' => $connection?->token_health ?? 'disconnected',
            'last_error' => $connection?->last_error,
            'connected_at' => $connection?->connected_at?->toIso8601String(),
            'expires_at' => null,
            'source' => null,
            'oauth_ready' => $oauthReady,
        ];
    }

    public static function oauthReady(): bool
    {
        return self::resolveClientId() !== '' && self::resolveClientSecret() !== '';
    }

    public static function oauthReadyForUser(int $userId): bool
    {
        if (OAuthAppConfigResolver::resolveForUser($userId, 'google')) {
            return true;
        }

        return self::oauthReady();
    }

    public static function sharedOAuthConfigured(): bool
    {
        return OAuthAppConfig::query()
            ->where('scope', OAuthAppConfig::SCOPE_SHARED)
            ->whereNull('user_id')
            ->where('provider', 'google')
            ->exists();
    }

    private static function isValidRefreshToken(string $refreshToken): bool
    {
        $refreshToken = self::normalizeRefreshToken($refreshToken);

        if ($refreshToken === '') {
            return false;
        }

        return ! str_starts_with($refreshToken, 'ya29.');
    }

    private static function normalizeRefreshToken(?string $refreshToken): string
    {
        return trim((string) $refreshToken);
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
