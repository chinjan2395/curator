<?php

namespace App\Http\Controllers;

use App\Models\OAuthAppConfig;
use App\Models\SocialCredential;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\OAuthAppConfigResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;

class SocialConnectController extends Controller
{
    private const YOUTUBE_SCOPES = [
        'https://www.googleapis.com/auth/youtube.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];

    private const GOOGLE_SCOPES = [
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];

    /**
     * Scopes for managed Pages: /me/accounts → page access token → /{page-id}/feed.
     * pages_read_user_content is commonly required alongside pages_read_engagement (Graph dependency / review).
     */
    private const FACEBOOK_SCOPES = ['public_profile', 'pages_show_list', 'pages_read_user_content', 'pages_read_engagement', 'pages_manage_posts', 'business_management'];

    /**
     * Instagram Graph API (Professional account linked to a Page): list Pages via /me/accounts,
     * then Page token for /{ig-user-id}/media. Page read scopes align with the Facebook feed flow.
     */
    private const INSTAGRAM_SCOPES = [
        'public_profile',
        'instagram_basic',
        'instagram_content_publish',
        'pages_show_list',
        'pages_read_user_content',
        'pages_read_engagement',
        'pages_manage_posts',
        'business_management',
    ];

    /** X / Twitter OAuth 2: read + write + media upload + offline refresh for native scheduling. users.email is not a valid X API v2 scope. */
    private const TWITTER_SCOPES = ['users.read', 'tweet.read', 'tweet.write', 'media.write', 'offline.access'];

    private const X_OAUTH_AUTHORIZE_URL = 'https://x.com/i/oauth2/authorize';

    private const X_OAUTH_TOKEN_URL = 'https://api.x.com/2/oauth2/token';
    private const TIKTOK_OAUTH_AUTHORIZE_URL = 'https://www.tiktok.com/v2/auth/authorize/';
    private const TIKTOK_OAUTH_TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';
    private const TIKTOK_SCOPES = ['user.info.basic', 'video.list', 'video.publish'];

    private const THREADS_OAUTH_AUTHORIZE_URL = 'https://threads.net/oauth/authorize';
    private const THREADS_OAUTH_TOKEN_URL = 'https://graph.threads.net/oauth/access_token';
    private const THREADS_LONG_LIVED_TOKEN_URL = 'https://graph.threads.net/access_token';
    private const THREADS_API_BASE = 'https://graph.threads.net/v1.0';
    private const THREADS_SCOPES = ['threads_basic', 'threads_content_publish'];

    private const LINKEDIN_OAUTH_AUTHORIZE_URL = 'https://www.linkedin.com/oauth/v2/authorization';

    private const LINKEDIN_OAUTH_TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    private const LINKEDIN_SCOPES = ['openid', 'profile', 'email', 'w_member_social'];

    private function normalizeAbsoluteUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            // Railway/custom env values are sometimes set without scheme.
            $trimmed = 'https://'.$trimmed;
        }

        return $trimmed;
    }

    private function backendUrl(string $path): string
    {
        $base = rtrim($this->normalizeAbsoluteUrl((string) config('app.url', '')), '/');
        $path = '/'.ltrim($path, '/');

        return $base.$path;
    }


    /**
     * Start OAuth flow. Returns auth_url for redirect.
     */
    public function connect(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:64'],
        ]);

        $provider = $validated['provider'];

        return match ($provider) {
            'youtube' => $this->connectYouTube($request),
            'google' => $this->connectGoogle($request),
            'facebook' => $this->connectFacebook($request),
            'instagram' => $this->connectInstagram($request),
            'twitter' => $this->connectTwitter($request),
            'tiktok' => $this->connectTikTok($request),
            'threads' => $this->connectThreads($request),
            'linkedin' => $this->connectLinkedIn($request),
            default => response()->json([
                'provider' => $provider,
                'auth_url' => null,
                'message' => 'OAuth not implemented for this provider yet.',
            ], 400),
        };
    }

    private function connectYouTube(Request $request): \Illuminate\Http\JsonResponse
    {
        $oauth = $this->oauthConfigForUser((int) $request->user()->id, 'google');
        if (! $oauth) {
            return response()->json(['message' => 'YouTube connect is not configured. Add Google OAuth client ID/secret in OAuth Apps.'], 503);
        }

        $state = $this->encryptState($request->user()->id, 'youtube');
        $redirectUrl = $this->normalizeAbsoluteUrl(
            $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/youtube')
        );

        $authUrl = Socialite::buildProvider(GoogleProvider::class, [
            'client_id' => $oauth->client_id,
            'client_secret' => $oauth->client_secret,
            'redirect' => $redirectUrl,
        ])
            ->stateless()
            ->scopes(self::YOUTUBE_SCOPES)
            ->redirectUrl($redirectUrl)
            ->with([
                'state' => $state,
                // Ensure we get a refresh_token so we can refresh access tokens.
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
            ])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['provider' => 'youtube', 'auth_url' => $authUrl]);
    }

    private function connectGoogle(Request $request): \Illuminate\Http\JsonResponse
    {
        $oauth = $this->oauthConfigForUser((int) $request->user()->id, 'google');
        if (! $oauth) {
            return response()->json(['message' => 'Google connect is not configured. Add Google OAuth client ID/secret in OAuth Apps.'], 503);
        }

        $state = $this->encryptState($request->user()->id, 'google');
        $redirectUrl = $this->normalizeAbsoluteUrl(
            $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/google')
        );

        $authUrl = Socialite::buildProvider(GoogleProvider::class, [
            'client_id' => $oauth->client_id,
            'client_secret' => $oauth->client_secret,
            'redirect' => $redirectUrl,
        ])
            ->stateless()
            ->scopes(self::GOOGLE_SCOPES)
            ->redirectUrl($redirectUrl)
            ->with([
                'state' => $state,
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
            ])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['provider' => 'google', 'auth_url' => $authUrl]);
    }

    private function connectFacebook(Request $request): \Illuminate\Http\JsonResponse
    {
        $oauth = $this->oauthConfigForUser((int) $request->user()->id, 'facebook');
        if (! $oauth) {
            return response()->json(['message' => 'Facebook connect is not configured. Add Facebook OAuth client ID/secret in OAuth Apps.'], 503);
        }

        $state = $this->encryptState($request->user()->id, 'facebook');
        $redirectUrl = $this->normalizeAbsoluteUrl(
            $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/facebook')
        );
        $this->setFacebookConfig($oauth, $redirectUrl);

        $driver = Socialite::driver('facebook')
            ->stateless()
            ->redirectUrl($redirectUrl)
            ->with(['state' => $state]);

        $configId = trim((string) config('services.facebook.login_config_id'));
        if ($configId !== '') {
            $authUrl = $driver->with(['config_id' => $configId])->redirect()->getTargetUrl();
        } else {
            // Use explicit scope set to avoid Socialite default `email` scope.
            $authUrl = $driver->setScopes(self::FACEBOOK_SCOPES)->redirect()->getTargetUrl();
        }

        return response()->json(['provider' => 'facebook', 'auth_url' => $authUrl]);
    }

    private function connectInstagram(Request $request): \Illuminate\Http\JsonResponse
    {
        $oauth = $this->oauthConfigForUser((int) $request->user()->id, 'facebook');
        if (! $oauth) {
            return response()->json(['message' => 'Instagram connect is not configured. Add Facebook OAuth client ID/secret in OAuth Apps.'], 503);
        }

        $state = $this->encryptState($request->user()->id, 'instagram');
        $redirectUrl = $this->normalizeAbsoluteUrl(
            $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/facebook')
        );
        $this->setFacebookConfig($oauth, $redirectUrl);

        $driver = Socialite::driver('facebook')
            ->stateless()
            ->redirectUrl($redirectUrl)
            ->with(['state' => $state]);

        $configId = trim((string) config('services.facebook.login_config_id'));
        if ($configId !== '') {
            $authUrl = $driver->with(['config_id' => $configId])->redirect()->getTargetUrl();
        } else {
            // Use explicit scope set to avoid Socialite default `email` scope.
            $authUrl = $driver->setScopes(self::INSTAGRAM_SCOPES)->redirect()->getTargetUrl();
        }

        return response()->json(['provider' => 'instagram', 'auth_url' => $authUrl]);
    }

    private function connectTwitter(Request $request): \Illuminate\Http\JsonResponse
    {
        $oauth = $this->oauthConfigForUser((int) $request->user()->id, 'twitter');
        if (! $oauth) {
            return response()->json(['message' => 'Twitter connect is not configured. Add Twitter OAuth client ID/secret in OAuth Apps.'], 503);
        }

        $redirectUrl = $this->normalizeAbsoluteUrl(
            $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/twitter')
        );

        // Socialite's X driver uses PKCE and always stores code_verifier in session inside
        // redirect(), but /api routes have no session. Embed PKCE verifier in encrypted state instead.
        $codeVerifier = Str::random(96);
        $state = Crypt::encryptString(json_encode([
            'user_id' => $request->user()->id,
            'provider' => 'twitter',
            'code_verifier' => $codeVerifier,
        ]));

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $oauth->client_id,
            'redirect_uri' => $redirectUrl,
            'scope' => implode(' ', self::TWITTER_SCOPES),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);

        $authUrl = self::X_OAUTH_AUTHORIZE_URL.'?'.$query;

        return response()->json(['provider' => 'twitter', 'auth_url' => $authUrl]);
    }

    private function connectTikTok(Request $request): \Illuminate\Http\JsonResponse
    {
        $oauth = $this->oauthConfigForUser((int) $request->user()->id, 'tiktok');
        if (! $oauth) {
            return response()->json(['message' => 'TikTok connect is not configured. Add TikTok OAuth client key/secret in OAuth Apps.'], 503);
        }

        $redirectUrl = $this->normalizeAbsoluteUrl(
            $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/tiktok')
        );
        $state = $this->encryptState($request->user()->id, 'tiktok');

        $query = http_build_query([
            'client_key' => $oauth->client_id,
            'redirect_uri' => $redirectUrl,
            'response_type' => 'code',
            'scope' => implode(',', self::TIKTOK_SCOPES),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return response()->json([
            'provider' => 'tiktok',
            'auth_url' => self::TIKTOK_OAUTH_AUTHORIZE_URL.'?'.$query,
        ]);
    }

    private function connectThreads(Request $request): \Illuminate\Http\JsonResponse
    {
        $oauth = $this->oauthConfigForUser((int) $request->user()->id, 'threads');
        if (! $oauth) {
            return response()->json(['message' => 'Threads connect is not configured. Add Threads OAuth client ID/secret in OAuth Apps.'], 503);
        }

        $redirectUrl = $this->normalizeAbsoluteUrl(
            $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/threads')
        );
        $state = $this->encryptState($request->user()->id, 'threads');

        $query = http_build_query([
            'client_id' => $oauth->client_id,
            'redirect_uri' => $redirectUrl,
            'response_type' => 'code',
            'scope' => implode(',', self::THREADS_SCOPES),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return response()->json([
            'provider' => 'threads',
            'auth_url' => self::THREADS_OAUTH_AUTHORIZE_URL.'?'.$query,
        ]);
    }

    private function connectLinkedIn(Request $request): \Illuminate\Http\JsonResponse
    {
        $oauth = $this->oauthConfigForUser((int) $request->user()->id, 'linkedin');
        if (! $oauth) {
            return response()->json(['message' => 'LinkedIn connect is not configured. Add LinkedIn OAuth client ID/secret in OAuth Apps.'], 503);
        }

        $redirectUrl = $this->normalizeAbsoluteUrl(
            $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/linkedin')
        );
        $state = $this->encryptState($request->user()->id, 'linkedin');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $oauth->client_id,
            'redirect_uri' => $redirectUrl,
            'state' => $state,
            'scope' => implode(' ', self::LINKEDIN_SCOPES),
        ], '', '&', PHP_QUERY_RFC3986);

        return response()->json([
            'provider' => 'linkedin',
            'auth_url' => self::LINKEDIN_OAUTH_AUTHORIZE_URL.'?'.$query,
        ]);
    }

    private function encryptState(int $userId, string $provider): string
    {
        return Crypt::encryptString(json_encode(['user_id' => $userId, 'provider' => $provider]));
    }

    private function frontendUrl(): string
    {
        return rtrim(
            $this->normalizeAbsoluteUrl((string) config('app.frontend_url', config('app.url'))),
            '/'
        );
    }

    private function redirectSuccess(string $provider): \Illuminate\Http\RedirectResponse
    {
        return redirect($this->frontendUrl() . '/credentials?connected=' . $provider);
    }

    private function redirectError(string $message = 'oauth_failed'): \Illuminate\Http\RedirectResponse
    {
        return redirect($this->frontendUrl() . '/credentials?error=oauth_failed&message=' . urlencode($message));
    }

    private function decodeState(Request $request): ?array
    {
        $state = $request->query('state');
        if (! $state) {
            return null;
        }
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
            if (! isset($payload['user_id'], $payload['provider'])) {
                return null;
            }
            return $payload;
        } catch (\Throwable $e) {
            Log::warning('OAuth state decrypt failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function saveCredential(int $userId, string $provider, $token, $refreshToken = null, $expiresIn = null, ?string $accountId = null, ?string $accountLabel = null): SocialCredential
    {
        $expiresAt = $expiresIn ? now()->addSeconds($expiresIn) : null;

        $cred = SocialCredential::updateOrCreate(
            ['user_id' => $userId, 'provider' => $provider, 'account_id' => $accountId],
            [
                'access_token' => $token,
                'expires_at' => $expiresAt,
                'account_label' => $accountLabel,
            ]
        );

        // Google may omit refresh_token on subsequent grants; don't overwrite a good one.
        if ($refreshToken) {
            $cred->refresh_token = $refreshToken;
            $cred->save();
        }

        return $cred;
    }

    public function callbackYouTube(Request $request)
    {
        try {
            $payload = $this->decodeState($request);
            if (! $payload || $payload['provider'] !== 'youtube') {
                return $this->redirectError('invalid_state');
            }

            $oauth = $this->oauthConfigForUser((int) $payload['user_id'], 'google');
            if (! $oauth) {
                return $this->redirectError('missing_google_oauth_config');
            }

            $googleUser = Socialite::buildProvider(GoogleProvider::class, [
                'client_id' => $oauth->client_id,
                'client_secret' => $oauth->client_secret,
                'redirect' => $this->normalizeAbsoluteUrl(
                    $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/youtube')
                ),
            ])
                ->stateless()
                ->redirectUrl(
                    $this->normalizeAbsoluteUrl(
                        $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/youtube')
                    )
                )
                ->user();

            $accountId = $googleUser->id;
            $accountLabel = $googleUser->name ?: $googleUser->email ?: null;

            $credential = $this->saveCredential(
                (int) $payload['user_id'],
                'youtube',
                $googleUser->token,
                $googleUser->refreshToken ?? null,
                $googleUser->expiresIn ?? null,
                $accountId,
                $accountLabel
            );

            if ($user = User::find((int) $payload['user_id'])) {
                ActivityLogger::log($user, 'credential.connected', 'Connected YouTube', 'credential', $credential->id, $credential->account_label ?? 'YouTube');
            }

            return $this->redirectSuccess('youtube');
        } catch (InvalidStateException $e) {
            Log::warning('OAuth state mismatch', ['message' => $e->getMessage()]);
            return $this->redirectError('state_mismatch');
        } catch (\Throwable $e) {
            Log::error('OAuth callback error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->redirectError($e->getMessage());
        }
    }

    public function callbackGoogle(Request $request)
    {
        try {
            $payload = $this->decodeState($request);
            if (! $payload || $payload['provider'] !== 'google') {
                return $this->redirectError('invalid_state');
            }

            $oauth = $this->oauthConfigForUser((int) $payload['user_id'], 'google');
            if (! $oauth) {
                return $this->redirectError('missing_google_oauth_config');
            }

            $googleUser = Socialite::buildProvider(GoogleProvider::class, [
                'client_id' => $oauth->client_id,
                'client_secret' => $oauth->client_secret,
                'redirect' => $this->normalizeAbsoluteUrl(
                    $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/google')
                ),
            ])
                ->stateless()
                ->redirectUrl(
                    $this->normalizeAbsoluteUrl(
                        $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/google')
                    )
                )
                ->user();

            $credential = $this->saveCredential(
                (int) $payload['user_id'],
                'google',
                $googleUser->token,
                $googleUser->refreshToken ?? null,
                $googleUser->expiresIn ?? null,
                $googleUser->id,
                $googleUser->name ?: $googleUser->email ?: null
            );

            if ($user = User::find((int) $payload['user_id'])) {
                ActivityLogger::log($user, 'credential.connected', 'Connected Google', 'credential', $credential->id, $credential->account_label ?? 'Google');
            }

            return $this->redirectSuccess('google');
        } catch (InvalidStateException $e) {
            Log::warning('OAuth state mismatch', ['message' => $e->getMessage()]);
            return $this->redirectError('state_mismatch');
        } catch (\Throwable $e) {
            Log::error('OAuth callback error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->redirectError($e->getMessage());
        }
    }

    public function callbackFacebook(Request $request)
    {
        try {
            $payload = $this->decodeState($request);
            if (! $payload || ! in_array($payload['provider'], ['facebook', 'instagram'], true)) {
                return $this->redirectError('invalid_state');
            }

            $oauth = $this->oauthConfigForUser((int) $payload['user_id'], 'facebook');
            if (! $oauth) {
                return $this->redirectError('missing_facebook_oauth_config');
            }

            $provider = $payload['provider'];
            $redirectUrl = $this->normalizeAbsoluteUrl(
                $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/facebook')
            );
            $this->setFacebookConfig($oauth, $redirectUrl);
            $fbUser = Socialite::driver('facebook')
                ->stateless()
                ->redirectUrl($redirectUrl)
                ->user();

            $token = $fbUser->token;
            $expiresIn = $fbUser->expiresIn;
            $longLived = $this->exchangeFacebookLongLivedToken($oauth, $token);
            if ($longLived) {
                $token = $longLived['access_token'];
                $expiresIn = $longLived['expires_in'];
            }

            $credential = $this->saveCredential(
                (int) $payload['user_id'],
                $provider,
                $token,
                $fbUser->refreshToken ?? null,
                $expiresIn,
                $fbUser->id,
                $fbUser->name ?: null
            );

            if ($user = User::find((int) $payload['user_id'])) {
                $providerLabel = ucfirst($provider);
                ActivityLogger::log($user, 'credential.connected', "Connected {$providerLabel}", 'credential', $credential->id, $credential->account_label ?? $providerLabel);
            }

            return $this->redirectSuccess($provider);
        } catch (InvalidStateException $e) {
            Log::warning('OAuth state mismatch', ['message' => $e->getMessage()]);
            return $this->redirectError('state_mismatch');
        } catch (\Throwable $e) {
            Log::error('OAuth callback error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->redirectError($e->getMessage());
        }
    }

    public function callbackTwitter(Request $request)
    {
        try {
            if ($request->query('error')) {
                return $this->redirectError((string) $request->query('error', 'oauth_denied'));
            }

            $payload = $this->decodeState($request);
            if (! $payload || $payload['provider'] !== 'twitter') {
                return $this->redirectError('invalid_state');
            }
            if (empty($payload['code_verifier']) || ! is_string($payload['code_verifier'])) {
                return $this->redirectError('invalid_state');
            }

            $code = $request->query('code');
            if (! $code || ! is_string($code)) {
                return $this->redirectError('missing_code');
            }

            $oauth = $this->oauthConfigForUser((int) $payload['user_id'], 'twitter');
            if (! $oauth) {
                return $this->redirectError('missing_twitter_oauth_config');
            }

            $redirectUrl = $this->normalizeAbsoluteUrl(
                $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/twitter')
            );

            $tokenResp = Http::asForm()
                ->withBasicAuth($oauth->client_id, $oauth->client_secret)
                ->acceptJson()
                ->post(self::X_OAUTH_TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUrl,
                    'code_verifier' => $payload['code_verifier'],
                    'client_id' => $oauth->client_id,
                ]);

            if (! $tokenResp->ok()) {
                Log::warning('X token exchange failed', [
                    'status' => $tokenResp->status(),
                    'body' => $tokenResp->body(),
                ]);

                return $this->redirectError('token_exchange_failed');
            }

            $accessToken = $tokenResp->json('access_token');
            if (! $accessToken || ! is_string($accessToken)) {
                return $this->redirectError('token_exchange_failed');
            }

            $expiresIn = $tokenResp->json('expires_in');
            $refreshToken = $tokenResp->json('refresh_token');

            $twitterAccountId = null;
            $twitterLabel = null;
            $meResp = Http::withToken($accessToken)->get('https://api.x.com/2/users/me', ['user.fields' => 'name,username']);
            if ($meResp->ok()) {
                $twitterAccountId = $meResp->json('data.id');
                $handle = $meResp->json('data.username');
                $name = $meResp->json('data.name');
                $twitterLabel = $handle ? "@{$handle}" . ($name ? " ({$name})" : '') : null;
            }

            $credential = $this->saveCredential(
                (int) $payload['user_id'],
                'twitter',
                $accessToken,
                is_string($refreshToken) ? $refreshToken : null,
                is_numeric($expiresIn) ? (int) $expiresIn : null,
                $twitterAccountId,
                $twitterLabel
            );

            $scopeRaw = $tokenResp->json('scope');
            if (is_string($scopeRaw) && trim($scopeRaw) !== '') {
                $credential->scopes = preg_split('/\s+/', trim($scopeRaw)) ?: [];
                $credential->save();
            }

            if ($user = User::find((int) $payload['user_id'])) {
                ActivityLogger::log($user, 'credential.connected', 'Connected Twitter / X', 'credential', $credential->id, $credential->account_label ?? 'Twitter');
            }

            return $this->redirectSuccess('twitter');
        } catch (InvalidStateException $e) {
            Log::warning('OAuth state mismatch', ['message' => $e->getMessage()]);

            return $this->redirectError('state_mismatch');
        } catch (\Throwable $e) {
            Log::error('OAuth callback error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return $this->redirectError($e->getMessage());
        }
    }

    public function callbackTikTok(Request $request)
    {
        try {
            if ($request->query('error')) {
                return $this->redirectError((string) $request->query('error', 'oauth_denied'));
            }

            $payload = $this->decodeState($request);
            if (! $payload || $payload['provider'] !== 'tiktok') {
                return $this->redirectError('invalid_state');
            }

            $code = $request->query('code');
            if (! $code || ! is_string($code)) {
                return $this->redirectError('missing_code');
            }

            $oauth = $this->oauthConfigForUser((int) $payload['user_id'], 'tiktok');
            if (! $oauth) {
                return $this->redirectError('missing_tiktok_oauth_config');
            }

            $redirectUrl = $this->normalizeAbsoluteUrl(
                $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/tiktok')
            );

            $tokenResp = Http::asForm()->post(self::TIKTOK_OAUTH_TOKEN_URL, [
                'client_key' => $oauth->client_id,
                'client_secret' => $oauth->client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUrl,
            ]);

            if (! $tokenResp->ok()) {
                Log::warning('TikTok token exchange failed', [
                    'status' => $tokenResp->status(),
                    'body' => $tokenResp->body(),
                ]);

                return $this->redirectError('token_exchange_failed');
            }

            $accessToken = $tokenResp->json('access_token');
            if (! $accessToken || ! is_string($accessToken)) {
                return $this->redirectError('token_exchange_failed');
            }

            $expiresIn = $tokenResp->json('expires_in');
            $refreshToken = $tokenResp->json('refresh_token');

            $tiktokAccountId = null;
            $tiktokLabel = null;
            $userResp = Http::withToken($accessToken)->get('https://open.tiktokapis.com/v2/user/info/', [
                'fields' => 'open_id,display_name,username',
            ]);
            if ($userResp->ok()) {
                $tiktokAccountId = $userResp->json('data.user.open_id');
                $username = $userResp->json('data.user.username');
                $displayName = $userResp->json('data.user.display_name');
                $tiktokLabel = $username ? "@{$username}" . ($displayName ? " ({$displayName})" : '') : $displayName;
            }

            $credential = $this->saveCredential(
                (int) $payload['user_id'],
                'tiktok',
                $accessToken,
                is_string($refreshToken) ? $refreshToken : null,
                is_numeric($expiresIn) ? (int) $expiresIn : null,
                $tiktokAccountId,
                $tiktokLabel
            );

            if ($user = User::find((int) $payload['user_id'])) {
                ActivityLogger::log($user, 'credential.connected', 'Connected TikTok', 'credential', $credential->id, $credential->account_label ?? 'TikTok');
            }

            return $this->redirectSuccess('tiktok');
        } catch (\Throwable $e) {
            Log::error('TikTok OAuth callback error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return $this->redirectError($e->getMessage());
        }
    }

    public function callbackThreads(Request $request)
    {
        try {
            if ($request->query('error')) {
                return $this->redirectError((string) $request->query('error', 'oauth_denied'));
            }

            $payload = $this->decodeState($request);
            if (! $payload || $payload['provider'] !== 'threads') {
                return $this->redirectError('invalid_state');
            }

            $code = $request->query('code');
            if (! $code || ! is_string($code)) {
                return $this->redirectError('missing_code');
            }

            $oauth = $this->oauthConfigForUser((int) $payload['user_id'], 'threads');
            if (! $oauth) {
                return $this->redirectError('missing_threads_oauth_config');
            }

            $redirectUrl = $this->normalizeAbsoluteUrl(
                $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/threads')
            );

            $tokenResp = Http::asForm()->acceptJson()->post(self::THREADS_OAUTH_TOKEN_URL, [
                'client_id' => $oauth->client_id,
                'client_secret' => $oauth->client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUrl,
            ]);

            if (! $tokenResp->ok()) {
                Log::warning('Threads token exchange failed', [
                    'status' => $tokenResp->status(),
                    'body' => $tokenResp->body(),
                ]);

                return $this->redirectError('token_exchange_failed');
            }

            $shortLivedToken = $tokenResp->json('access_token');
            if (! $shortLivedToken || ! is_string($shortLivedToken)) {
                return $this->redirectError('token_exchange_failed');
            }

            // Exchange for long-lived (~60 day) token immediately.
            $accessToken = $shortLivedToken;
            $expiresIn = null;
            $longLived = Http::acceptJson()->get(self::THREADS_LONG_LIVED_TOKEN_URL, [
                'grant_type' => 'th_exchange_token',
                'client_secret' => $oauth->client_secret,
                'access_token' => $shortLivedToken,
            ]);
            if ($longLived->ok()) {
                $llToken = $longLived->json('access_token');
                $llExpires = $longLived->json('expires_in');
                if (is_string($llToken) && $llToken !== '') {
                    $accessToken = $llToken;
                    $expiresIn = is_numeric($llExpires) ? (int) $llExpires : null;
                }
            } else {
                Log::warning('Threads long-lived token exchange failed', [
                    'status' => $longLived->status(),
                    'body' => $longLived->body(),
                ]);
            }

            $threadsAccountId = null;
            $threadsLabel = null;
            $meResp = Http::withToken($accessToken)->get(self::THREADS_API_BASE.'/me', [
                'fields' => 'id,username,name',
            ]);
            if ($meResp->ok()) {
                $threadsAccountId = $meResp->json('id');
                $username = $meResp->json('username');
                $name = $meResp->json('name');
                $threadsLabel = $username ? "@{$username}" . ($name ? " ({$name})" : '') : ($name ?: null);
            }

            $credential = $this->saveCredential(
                (int) $payload['user_id'],
                'threads',
                $accessToken,
                null,
                $expiresIn,
                $threadsAccountId,
                $threadsLabel
            );

            if ($user = User::find((int) $payload['user_id'])) {
                ActivityLogger::log($user, 'credential.connected', 'Connected Threads', 'credential', $credential->id, $credential->account_label ?? 'Threads');
            }

            return $this->redirectSuccess('threads');
        } catch (\Throwable $e) {
            Log::error('Threads OAuth callback error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return $this->redirectError($e->getMessage());
        }
    }

    public function callbackLinkedIn(Request $request)
    {
        try {
            if ($request->query('error')) {
                return $this->redirectError((string) $request->query('error', 'oauth_denied'));
            }

            $payload = $this->decodeState($request);
            if (! $payload || $payload['provider'] !== 'linkedin') {
                return $this->redirectError('invalid_state');
            }

            $code = $request->query('code');
            if (! $code || ! is_string($code)) {
                return $this->redirectError('missing_code');
            }

            $oauth = $this->oauthConfigForUser((int) $payload['user_id'], 'linkedin');
            if (! $oauth) {
                return $this->redirectError('missing_linkedin_oauth_config');
            }

            $redirectUrl = $this->normalizeAbsoluteUrl(
                $oauth->redirect_uri ?: $this->backendUrl('/api/social/callback/linkedin')
            );

            $tokenResp = Http::asForm()
                ->acceptJson()
                ->timeout(30)
                ->post(self::LINKEDIN_OAUTH_TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'client_id' => $oauth->client_id,
                    'client_secret' => $oauth->client_secret,
                    'redirect_uri' => $redirectUrl,
                ]);

            if (! $tokenResp->ok()) {
                Log::warning('LinkedIn token exchange failed', [
                    'status' => $tokenResp->status(),
                    'body' => $tokenResp->body(),
                ]);

                return $this->redirectError('token_exchange_failed');
            }

            $accessToken = $tokenResp->json('access_token');
            if (! is_string($accessToken) || $accessToken === '') {
                return $this->redirectError('token_exchange_failed');
            }

            $refreshToken = $tokenResp->json('refresh_token');
            $expiresIn = $tokenResp->json('expires_in');

            $personId = null;
            $accountLabel = null;
            $userResp = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->get('https://api.linkedin.com/v2/userinfo');

            if ($userResp->ok()) {
                $personId = $userResp->json('sub');
                $name = trim((string) ($userResp->json('name') ?? ''));
                $email = trim((string) ($userResp->json('email') ?? ''));
                $accountLabel = $name !== '' ? $name : ($email !== '' ? $email : null);
            }

            $credential = $this->saveCredential(
                (int) $payload['user_id'],
                'linkedin',
                $accessToken,
                is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null,
                is_numeric($expiresIn) ? (int) $expiresIn : null,
                is_string($personId) || is_numeric($personId) ? (string) $personId : null,
                $accountLabel,
            );

            $credential->scopes = self::LINKEDIN_SCOPES;
            $credential->status = 'active';
            $credential->token_health = 'valid';
            $credential->save();

            if ($user = User::find((int) $payload['user_id'])) {
                ActivityLogger::log($user, 'credential.connected', 'Connected LinkedIn', 'credential', $credential->id, $credential->account_label ?? 'LinkedIn');
            }

            return $this->redirectSuccess('linkedin');
        } catch (\Throwable $e) {
            Log::error('LinkedIn OAuth callback error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return $this->redirectError($e->getMessage());
        }
    }

    public function disconnect(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $credential = $request->user()
            ->socialCredentials()
            ->where('id', $validated['id'])
            ->first();

        if ($credential) {
            $providerLabel = ucfirst($credential->provider);
            ActivityLogger::log($request->user(), 'credential.disconnected', "Disconnected {$providerLabel}", 'credential', $credential->id, $credential->account_label ?? $providerLabel);
            $credential->delete();
            $deleted = 1;
        } else {
            $deleted = 0;
        }

        return response()->json(['deleted' => $deleted, 'message' => 'Disconnected']);
    }

    private function oauthConfigForUser(int $userId, string $provider): ?OAuthAppConfig
    {
        return OAuthAppConfigResolver::resolveForUser($userId, $provider);
    }

    private function setFacebookConfig(OAuthAppConfig $oauth, string $redirectUrl): void
    {
        Config::set('services.facebook.client_id', $oauth->client_id);
        Config::set('services.facebook.client_secret', $oauth->client_secret);
        Config::set('services.facebook.redirect', $redirectUrl);
    }

    /**
     * @return array{access_token: string, expires_in: int}|null
     */
    private function exchangeFacebookLongLivedToken(OAuthAppConfig $oauth, string $shortLivedToken): ?array
    {
        $response = Http::get('https://graph.facebook.com/v23.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $oauth->client_id,
            'client_secret' => $oauth->client_secret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        if (! $response->ok()) {
            Log::warning('Facebook long-lived token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $accessToken = $response->json('access_token');
        $expiresIn = $response->json('expires_in');
        if (! $accessToken || $expiresIn === null) {
            return null;
        }

        return [
            'access_token' => (string) $accessToken,
            'expires_in' => (int) $expiresIn,
        ];
    }

}
