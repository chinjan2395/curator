<?php

namespace App\Models;

use App\Support\OAuthAppConfigResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class SocialCredential extends Model
{
    /** Consider token expired this many seconds before expires_at. */
    private const EXPIRY_BUFFER_SECONDS = 300;

    protected $fillable = [
        'user_id',
        'provider',
        'account_id',
        'account_label',
        'profile_image_url',
        'follower_count',
        'scopes',
        'token_health',
        'last_metadata_synced_at',
        'access_token',
        'refresh_token',
        'expires_at',
        'status',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'access_token_encrypted',
        'refresh_token_encrypted',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_metadata_synced_at' => 'datetime',
        'scopes' => 'array',
        'follower_count' => 'integer',
    ];

    public function setAccessTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['access_token'] = null;
            $this->attributes['access_token_encrypted'] = null;

            return;
        }
        $this->attributes['access_token_encrypted'] = Crypt::encryptString($value);
        $this->attributes['access_token'] = '';
    }

    public function getAccessTokenAttribute(): ?string
    {
        if (! empty($this->attributes['access_token_encrypted'])) {
            try {
                return Crypt::decryptString((string) $this->attributes['access_token_encrypted']);
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->attributes['access_token'] ?? null;
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['refresh_token'] = null;
            $this->attributes['refresh_token_encrypted'] = null;

            return;
        }
        $this->attributes['refresh_token_encrypted'] = Crypt::encryptString($value);
        $this->attributes['refresh_token'] = '';
    }

    public function getRefreshTokenAttribute(): ?string
    {
        if (! empty($this->attributes['refresh_token_encrypted'])) {
            try {
                return Crypt::decryptString((string) $this->attributes['refresh_token_encrypted']);
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->attributes['refresh_token'] ?? null;
    }

    public function refreshTokenHealth(): void
    {
        if ($this->status === 'disconnected') {
            $this->token_health = 'disconnected';

            return;
        }

        $token = $this->getValidAccessToken();
        if ($token) {
            $this->token_health = 'valid';
        } elseif ($this->refresh_token || $this->provider === 'threads') {
            $this->token_health = 'needs_reauth';
        } else {
            $this->token_health = 'expired';
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (self $credential): void {
            $feedIds = Feed::where('social_credential_id', $credential->id)->pluck('id');
            Post::whereIn('feed_id', $feedIds)->delete();
            Feed::whereIn('id', $feedIds)->delete();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function feeds()
    {
        return $this->hasMany(Feed::class, 'social_credential_id');
    }

    /**
     * Return a valid access token for API calls, refreshing when supported if expired.
     * Returns null if the credential cannot provide a valid token (e.g. no refresh_token).
     */
    public function getValidAccessToken(): ?string
    {
        return match ($this->provider) {
            'youtube' => $this->getValidYouTubeAccessToken(),
            'twitter' => $this->getValidTwitterAccessToken(),
            'tiktok' => $this->getValidTikTokAccessToken(),
            'threads' => $this->getValidThreadsAccessToken(),
            default => $this->access_token,
        };
    }

    private function getValidYouTubeAccessToken(): ?string
    {
        $expiresAt = $this->expires_at;
        $now = now();
        $expired = ! $expiresAt || $expiresAt->copy()->subSeconds(self::EXPIRY_BUFFER_SECONDS)->isPast();

        if (! $expired) {
            return $this->access_token;
        }

        if (empty($this->refresh_token)) {
            return null;
        }

        $oauth = OAuthAppConfigResolver::resolveForUser((int) $this->user_id, 'google');

        $clientId = $oauth?->client_id;
        $clientSecret = $oauth?->client_secret;
        if (! $clientId || ! $clientSecret) {
            throw new \RuntimeException('Google OAuth app credentials are not configured.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $this->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            // invalid_grant = user revoked access; everything else is transient
            if ($response->json('error') === 'invalid_grant') {
                return null;
            }
            throw new \RuntimeException('YouTube token refresh failed: ' . ($response->json('error') ?? $response->status()));
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);
        if (! $accessToken) {
            throw new \RuntimeException('YouTube token refresh returned no access_token.');
        }

        $this->access_token = $accessToken;
        $this->expires_at = $now->copy()->addSeconds($expiresIn);
        $this->save();

        return $this->access_token;
    }

    private function getValidTwitterAccessToken(): ?string
    {
        $expiresAt = $this->expires_at;
        $now = now();
        $expired = ! $expiresAt || $expiresAt->copy()->subSeconds(self::EXPIRY_BUFFER_SECONDS)->isPast();

        if (! $expired) {
            return $this->access_token;
        }

        if (empty($this->refresh_token)) {
            return null;
        }

        $oauth = OAuthAppConfigResolver::resolveForUser((int) $this->user_id, 'twitter');

        $clientId = $oauth?->client_id;
        $clientSecret = $oauth?->client_secret;
        if (! $clientId || ! $clientSecret) {
            throw new \RuntimeException('Twitter OAuth app credentials are not configured.');
        }

        $response = Http::asForm()->withBasicAuth($clientId, $clientSecret)->post('https://api.x.com/2/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh_token,
            'client_id' => $clientId,
        ]);

        if (! $response->successful()) {
            // 401 with invalid_request means the refresh token has been revoked or rotated away
            if ($response->status() === 401 || $response->json('error') === 'invalid_request') {
                return null;
            }
            throw new \RuntimeException('Twitter token refresh failed: ' . ($response->json('error') ?? $response->status()));
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 7200);
        if (! $accessToken) {
            throw new \RuntimeException('Twitter token refresh returned no access_token.');
        }

        $this->access_token = $accessToken;
        $this->expires_at = $now->copy()->addSeconds($expiresIn);
        $newRefresh = $response->json('refresh_token');
        if (is_string($newRefresh) && $newRefresh !== '') {
            $this->refresh_token = $newRefresh;
        }
        $this->save();

        return $this->access_token;
    }

    /**
     * Threads long-lived tokens last ~60 days and can be refreshed by calling
     * /refresh_access_token with the existing access_token (no separate refresh_token).
     */
    private function getValidThreadsAccessToken(): ?string
    {
        $expiresAt = $this->expires_at;
        $now = now();
        $expired = $expiresAt && $expiresAt->copy()->subSeconds(self::EXPIRY_BUFFER_SECONDS)->isPast();

        if (! $expired) {
            return $this->access_token;
        }

        if (empty($this->access_token)) {
            return null;
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->get('https://graph.threads.net/refresh_access_token', [
                'grant_type' => 'th_refresh_token',
                'access_token' => $this->access_token,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Threads token refresh failed: ' . ($response->json('error') ?? $response->status()));
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 5184000);
        if (! $accessToken) {
            throw new \RuntimeException('Threads token refresh returned no access_token.');
        }

        $this->access_token = $accessToken;
        $this->expires_at = $now->copy()->addSeconds($expiresIn);
        $this->save();

        return $this->access_token;
    }

    private function getValidTikTokAccessToken(): ?string
    {
        $expiresAt = $this->expires_at;
        $now = now();
        $expired = ! $expiresAt || $expiresAt->copy()->subSeconds(self::EXPIRY_BUFFER_SECONDS)->isPast();

        if (! $expired) {
            return $this->access_token;
        }

        if (empty($this->refresh_token)) {
            return null;
        }

        $oauth = OAuthAppConfigResolver::resolveForUser((int) $this->user_id, 'tiktok');

        $clientKey = $oauth?->client_id;
        $clientSecret = $oauth?->client_secret;
        if (! $clientKey || ! $clientSecret) {
            throw new \RuntimeException('TikTok OAuth app credentials are not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => $clientKey,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refresh_token,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('TikTok token refresh failed: ' . ($response->json('error') ?? $response->status()));
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 86400);
        if (! $accessToken) {
            throw new \RuntimeException('TikTok token refresh returned no access_token.');
        }

        $this->access_token = $accessToken;
        $this->expires_at = $now->copy()->addSeconds($expiresIn);
        $newRefresh = $response->json('refresh_token');
        if (is_string($newRefresh) && $newRefresh !== '') {
            $this->refresh_token = $newRefresh;
        }
        $this->save();

        return $this->access_token;
    }
}
