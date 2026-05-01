<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'facebook', 'github', 'twitter'];

    private const X_OAUTH_AUTHORIZE_URL = 'https://x.com/i/oauth2/authorize';
    private const X_OAUTH_TOKEN_URL = 'https://api.x.com/2/oauth2/token';
    private const TWITTER_LOGIN_SCOPES = ['users.read', 'tweet.read', 'offline.access'];

    /**
     * Facebook Login for Business: Meta requires email + public_profile plus at least one supported business permission.
     *
     * @see https://developers.facebook.com/documentation/facebook-login/facebook-login-for-business
     */
    private const FACEBOOK_LOGIN_SCOPES = ['email', 'public_profile', 'pages_show_list'];

    /**
     * Which social login providers have required .env credentials (client id + secret).
     */
    public function providers(): JsonResponse
    {
        $enabled = [];
        if ($this->isGoogleLoginConfigured()) {
            $enabled[] = 'google';
        }
        if ($this->isFacebookLoginConfigured()) {
            $enabled[] = 'facebook';
        }
        if ($this->isGithubLoginConfigured()) {
            $enabled[] = 'github';
        }
        if ($this->isTwitterLoginConfigured()) {
            $enabled[] = 'twitter';
        }

        return response()->json([
            'providers' => $enabled,
            'social_login_notice' => $this->socialLoginConfigurationNotice(),
        ]);
    }

    /**
     * Non-sensitive hint when Facebook login env looks mis-set (helps avoid confusing Google vs Meta IDs).
     */
    private function socialLoginConfigurationNotice(): ?string
    {
        $fbClientId = (string) config('services.facebook.client_id');
        $fbCollapsed = preg_replace('/\s+/u', '', trim($fbClientId)) ?? '';

        if ($fbCollapsed === '') {
            return null;
        }

        if ($this->isFacebookLoginConfigured()) {
            return null;
        }

        if (str_contains($fbClientId, 'apps.googleusercontent.com')) {
            return 'FACEBOOK_CLIENT_ID is set to a Google OAuth client ID. Facebook login needs your numeric Meta App ID from developers.facebook.com → App settings → Basic, and FACEBOOK_CLIENT_SECRET from that same Meta app (not Google credentials).';
        }

        if (! ctype_digit($fbCollapsed)) {
            return 'FACEBOOK_CLIENT_ID must be the numeric Meta App ID only. Copy it from developers.facebook.com → Your app → App settings → Basic.';
        }

        if (trim((string) config('services.facebook.client_secret')) === '') {
            return 'FACEBOOK_CLIENT_SECRET is missing. Add the App Secret from the same Meta app on developers.facebook.com.';
        }

        return null;
    }

    public function redirect(string $provider)
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return redirect($this->frontendUrl('/login?error=unsupported_provider'));
        }

        if ($provider === 'twitter') {
            return $this->twitterRedirect();
        }

        if ($blocking = $this->ensureProviderCredentialsForLogin($provider)) {
            return $blocking;
        }

        $driver = Socialite::driver($provider)
            ->redirectUrl($this->callbackUrl($provider))
            ->stateless();

        if ($provider === 'facebook') {
            $driver = $this->facebookLoginDriver($driver);
        }

        if ($provider === 'github') {
            $driver = $driver->scopes(['user:email']);
        }

        return $driver->redirect();
    }

    public function callback(string $provider, Request $request)
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            return redirect($this->frontendUrl('/login?error=unsupported_provider'));
        }

        if ($provider === 'twitter') {
            return $this->twitterCallback($request);
        }

        return $this->socialiteCallback($provider, $request);
    }

    private function socialiteCallback(string $provider, Request $request)
    {
        try {
            $driver = Socialite::driver($provider)
                ->redirectUrl($this->callbackUrl($provider))
                ->stateless();

            if ($provider === 'facebook') {
                $driver = $this->facebookLoginDriver($driver);
            }

            $socialUser = $driver->user();
        } catch (\Exception) {
            return redirect($this->frontendUrl('/login?error=social_auth_failed'));
        }

        $email = $socialUser->getEmail();
        if (! $email) {
            return redirect($this->frontendUrl('/login?error=email_required'));
        }

        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: 'User';
        $user = $this->findOrCreateUser($email, $name, $provider, (string) $socialUser->getId());

        if ($user->isDeactivated()) {
            return redirect($this->frontendUrl('/login?error=account_deactivated'));
        }

        $token = $user->createToken('auth')->plainTextToken;

        return redirect($this->frontendUrl('/auth/social/callback?token='.urlencode($token)));
    }

    /**
     * Facebook Login for Business: use a dashboard Configuration ID when set (Meta recommends not mixing scopes).
     * Otherwise request explicit scopes including at least one supported business permission per Meta requirements.
     *
     * @param  \Laravel\Socialite\Contracts\Provider  $driver
     */
    private function facebookLoginDriver(\Laravel\Socialite\Contracts\Provider $driver): \Laravel\Socialite\Contracts\Provider
    {
        $configId = trim((string) config('services.facebook.login_config_id'));
        if ($configId !== '') {
            return $driver->with(['config_id' => $configId]);
        }

        return $driver->scopes(self::FACEBOOK_LOGIN_SCOPES);
    }

    /**
     * Social login reads OAuth client IDs from config/services.php (.env), not from per-user OAuth apps.
     */
    private function ensureProviderCredentialsForLogin(string $provider): ?RedirectResponse
    {
        return match ($provider) {
            'facebook' => $this->ensureFacebookLoginCredentials(),
            'google' => $this->ensureGoogleLoginCredentials(),
            'github' => $this->ensureGithubLoginCredentials(),
            default => null,
        };
    }

    private function isGoogleLoginConfigured(): bool
    {
        return trim((string) config('services.google.client_id')) !== ''
            && trim((string) config('services.google.client_secret')) !== '';
    }

    private function isFacebookLoginConfigured(): bool
    {
        $id = trim((string) config('services.facebook.client_id'));
        $secret = trim((string) config('services.facebook.client_secret'));

        return $id !== '' && ctype_digit($id) && $secret !== '';
    }

    private function isGithubLoginConfigured(): bool
    {
        return trim((string) config('services.github.client_id')) !== ''
            && trim((string) config('services.github.client_secret')) !== '';
    }

    private function isTwitterLoginConfigured(): bool
    {
        return trim((string) config('services.x.client_id')) !== ''
            && trim((string) config('services.x.client_secret')) !== '';
    }

    private function ensureFacebookLoginCredentials(): ?RedirectResponse
    {
        $id = trim((string) config('services.facebook.client_id'));
        if ($id === '') {
            return redirect($this->frontendUrl('/login?error=facebook_not_configured'));
        }

        // Meta App IDs are numeric; a hex App Secret or unresolved ${VAR} in .env triggers "Invalid App ID" from Facebook.
        if (! ctype_digit($id)) {
            return redirect($this->frontendUrl('/login?error=facebook_invalid_app_id'));
        }

        if (trim((string) config('services.facebook.client_secret')) === '') {
            return redirect($this->frontendUrl('/login?error=facebook_not_configured'));
        }

        return null;
    }

    private function ensureGoogleLoginCredentials(): ?RedirectResponse
    {
        if ($this->isGoogleLoginConfigured()) {
            return null;
        }

        return redirect($this->frontendUrl('/login?error=google_not_configured'));
    }

    private function ensureGithubLoginCredentials(): ?RedirectResponse
    {
        if ($this->isGithubLoginConfigured()) {
            return null;
        }

        return redirect($this->frontendUrl('/login?error=github_not_configured'));
    }

    private function twitterRedirect()
    {
        if (! $this->isTwitterLoginConfigured()) {
            return redirect($this->frontendUrl('/login?error=twitter_not_configured'));
        }

        $clientId = trim((string) config('services.x.client_id'));

        $codeVerifier = Str::random(96);
        $state = Crypt::encryptString(json_encode([
            'type' => 'login',
            'provider' => 'twitter',
            'code_verifier' => $codeVerifier,
        ]));

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->callbackUrl('twitter'),
            'scope' => implode(' ', self::TWITTER_LOGIN_SCOPES),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect(self::X_OAUTH_AUTHORIZE_URL.'?'.$query);
    }

    private function twitterCallback(Request $request)
    {
        if ($request->query('error')) {
            return redirect($this->frontendUrl('/login?error=social_auth_failed'));
        }

        $stateParam = $request->query('state');
        if (! $stateParam) {
            return redirect($this->frontendUrl('/login?error=invalid_state'));
        }

        try {
            $payload = json_decode(Crypt::decryptString($stateParam), true);
        } catch (\Exception) {
            return redirect($this->frontendUrl('/login?error=invalid_state'));
        }

        if (
            ! is_array($payload) ||
            ($payload['type'] ?? null) !== 'login' ||
            ($payload['provider'] ?? null) !== 'twitter'
        ) {
            return redirect($this->frontendUrl('/login?error=invalid_state'));
        }

        $codeVerifier = $payload['code_verifier'] ?? null;
        $code = $request->query('code');

        if (! $codeVerifier || ! $code) {
            return redirect($this->frontendUrl('/login?error=missing_code'));
        }

        $clientId = config('services.x.client_id');
        $clientSecret = config('services.x.client_secret');

        $tokenResp = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->acceptJson()
            ->post(self::X_OAUTH_TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->callbackUrl('twitter'),
                'code_verifier' => $codeVerifier,
                'client_id' => $clientId,
            ]);

        if (! $tokenResp->ok()) {
            return redirect($this->frontendUrl('/login?error=token_exchange_failed'));
        }

        $accessToken = $tokenResp->json('access_token');
        if (! $accessToken || ! is_string($accessToken)) {
            return redirect($this->frontendUrl('/login?error=token_exchange_failed'));
        }

        $meResp = Http::withToken($accessToken)
            ->get('https://api.x.com/2/users/me', ['user.fields' => 'id,name,username']);

        if (! $meResp->ok()) {
            return redirect($this->frontendUrl('/login?error=user_fetch_failed'));
        }

        $twitterId = (string) $meResp->json('data.id');
        $name = $meResp->json('data.name') ?: $meResp->json('data.username') ?: 'Twitter User';
        // Twitter OAuth 2 doesn't provide email; use synthetic email keyed to the user's Twitter ID
        $email = "twitter_{$twitterId}@social.curator.local";

        $user = $this->findOrCreateUser($email, $name, 'twitter', $twitterId);

        if ($user->isDeactivated()) {
            return redirect($this->frontendUrl('/login?error=account_deactivated'));
        }

        $token = $user->createToken('auth')->plainTextToken;

        return redirect($this->frontendUrl('/auth/social/callback?token='.urlencode($token)));
    }

    private function findOrCreateUser(string $email, string $name, string $provider, string $providerId): User
    {
        // Find by provider ID first (handles email changes and Twitter synthetic emails)
        $user = User::where('social_provider', $provider)
            ->where('social_provider_id', $providerId)
            ->first();

        if ($user) {
            return $user;
        }

        // Find existing account by email and link it
        $user = User::where('email', $email)->first();
        if ($user) {
            if (! $user->social_provider) {
                $user->update(['social_provider' => $provider, 'social_provider_id' => $providerId]);
            }

            return $user;
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => null,
            'social_provider' => $provider,
            'social_provider_id' => $providerId,
        ]);
    }

    private function callbackUrl(string $provider): string
    {
        return rtrim((string) config('app.url'), '/')."/api/auth/social/{$provider}/callback";
    }

    private function frontendUrl(string $path = ''): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        if ($base && ! preg_match('#^https?://#i', $base)) {
            $base = 'https://'.$base;
        }

        return $base.$path;
    }
}
