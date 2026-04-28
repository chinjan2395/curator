<?php

namespace App\Models;

use App\Models\OAuthAppConfig;
use App\Support\OAuthAppConfigResolver;
use Illuminate\Database\Eloquent\Model;
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
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
            return null;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $this->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);
        if (! $accessToken) {
            return null;
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
            return null;
        }

        $response = Http::asForm()->withBasicAuth($clientId, $clientSecret)->post('https://api.x.com/2/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh_token,
            'client_id' => $clientId,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 7200);
        if (! $accessToken) {
            return null;
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
            return null;
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 5184000);
        if (! $accessToken) {
            return null;
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
            return null;
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
            return null;
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 86400);
        if (! $accessToken) {
            return null;
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
