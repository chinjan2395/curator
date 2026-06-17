<?php

namespace App\Services\Storage;

use App\Models\GoogleDriveConnection;
use App\Models\OAuthAppConfig;
use App\Support\GoogleDriveConfig;
use App\Support\OAuthAppConfigResolver;
use Illuminate\Support\Facades\Http;

class GoogleDriveTokenService
{
    private const EXPIRY_BUFFER_SECONDS = 300;

    public function refreshConnectionHealth(?GoogleDriveConnection $connection = null): bool
    {
        $connection ??= GoogleDriveConnection::current();

        if (! $connection || empty($connection->refresh_token)) {
            return false;
        }

        $token = $this->refreshAccessToken($connection);

        return $token !== null;
    }

    public function refreshAccessToken(GoogleDriveConnection $connection): ?string
    {
        if (empty($connection->refresh_token)) {
            $connection->markNeedsReauth('Refresh token missing.');

            return null;
        }

        $oauth = OAuthAppConfig::query()
            ->where('scope', OAuthAppConfig::SCOPE_SHARED)
            ->whereNull('user_id')
            ->where('provider', 'google')
            ->first();

        if (! $oauth && $connection->connected_by_user_id) {
            $oauth = OAuthAppConfigResolver::resolveForUser((int) $connection->connected_by_user_id, 'google');
        }

        $clientId = $oauth?->client_id ?: GoogleDriveConfig::resolveClientId();
        $clientSecret = $oauth?->client_secret ?: GoogleDriveConfig::resolveClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            $connection->markNeedsReauth('Google OAuth client credentials are not configured.');

            return null;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            $message = $response->json('error_description')
                ?? $response->json('error')
                ?? 'Token refresh failed.';

            if ($response->json('error') === 'invalid_grant') {
                $connection->markNeedsReauth($message);
            } else {
                $connection->token_health = 'error';
                $connection->last_error = $message;
                $connection->save();
            }

            return null;
        }

        $accessToken = $response->json('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            $connection->markNeedsReauth('Google token refresh returned no access token.');

            return null;
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        $connection->access_token = $accessToken;
        $connection->expires_at = now()->addSeconds($expiresIn);

        $newRefresh = $response->json('refresh_token');
        if (is_string($newRefresh) && $newRefresh !== '') {
            $connection->refresh_token = $newRefresh;
        }

        $connection->markValid();

        return $accessToken;
    }

    public function getValidAccessToken(?GoogleDriveConnection $connection = null): ?string
    {
        $connection ??= GoogleDriveConnection::current();

        if (! $connection || empty($connection->refresh_token)) {
            return null;
        }

        $expiresAt = $connection->expires_at;
        $expired = ! $expiresAt
            || $expiresAt->copy()->subSeconds(self::EXPIRY_BUFFER_SECONDS)->isPast();

        if (! $expired && $connection->access_token) {
            return $connection->access_token;
        }

        return $this->refreshAccessToken($connection);
    }
}
