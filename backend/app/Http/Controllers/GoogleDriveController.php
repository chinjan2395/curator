<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\GoogleDriveConnection;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\GoogleDriveConfig;
use App\Support\OAuthAppConfigResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleDriveController extends Controller
{
    private const DRIVE_SCOPES = [
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];

    public function status(Request $request): JsonResponse
    {
        return ApiResponse::success(GoogleDriveConfig::status());
    }

    public function connect(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403, 'Admin access required to connect Google Drive storage.');

        $oauth = OAuthAppConfigResolver::resolveForUser((int) $user->id, 'google');
        if (! $oauth) {
            return ApiResponse::error(
                'Google OAuth is not configured. Add Google client ID and secret in OAuth Apps first.',
                null,
                503
            );
        }

        $redirectUrl = $this->driveCallbackUrl($oauth->redirect_uri);
        $state = Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'provider' => 'google_drive',
        ]));

        $authUrl = Socialite::buildProvider(GoogleProvider::class, [
            'client_id' => $oauth->client_id,
            'client_secret' => $oauth->client_secret,
            'redirect' => $redirectUrl,
        ])
            ->stateless()
            ->scopes(self::DRIVE_SCOPES)
            ->redirectUrl($redirectUrl)
            ->with([
                'state' => $state,
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
            ])
            ->redirect()
            ->getTargetUrl();

        return ApiResponse::success([
            'auth_url' => $authUrl,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $payload = $this->decodeState($request);
            if (! $payload || ($payload['provider'] ?? null) !== 'google_drive') {
                return $this->redirectError('invalid_state');
            }

            $userId = (int) $payload['user_id'];
            $oauth = OAuthAppConfigResolver::resolveForUser($userId, 'google');
            if (! $oauth) {
                return $this->redirectError('missing_google_oauth_config');
            }

            $redirectUrl = $this->driveCallbackUrl($oauth->redirect_uri);

            $googleUser = Socialite::buildProvider(GoogleProvider::class, [
                'client_id' => $oauth->client_id,
                'client_secret' => $oauth->client_secret,
                'redirect' => $redirectUrl,
            ])
                ->stateless()
                ->redirectUrl($redirectUrl)
                ->user();

            $refreshToken = $googleUser->refreshToken ?? null;
            if (! is_string($refreshToken) || $refreshToken === '') {
                return $this->redirectError('missing_refresh_token');
            }

            GoogleDriveConnection::query()->delete();

            $connection = GoogleDriveConnection::create([
                'connected_by_user_id' => $userId,
                'account_email' => $googleUser->email,
                'account_label' => $googleUser->name ?: $googleUser->email,
                'refresh_token' => $refreshToken,
                'access_token' => $googleUser->token,
                'expires_at' => $googleUser->expiresIn ? now()->addSeconds((int) $googleUser->expiresIn) : null,
                'token_health' => 'valid',
                'last_error' => null,
                'connected_at' => now(),
            ]);

            if ($user = User::find($userId)) {
                ActivityLogger::log(
                    $user,
                    'google_drive.connected',
                    'Connected Google Drive for asset storage',
                    'google_drive_connection',
                    $connection->id,
                    $connection->account_label ?? 'Google Drive'
                );
            }

            return $this->redirectSuccess();
        } catch (InvalidStateException $e) {
            Log::warning('Google Drive OAuth state mismatch', ['message' => $e->getMessage()]);

            return $this->redirectError('state_mismatch');
        } catch (\Throwable $e) {
            Log::error('Google Drive OAuth callback error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->redirectError($e->getMessage());
        }
    }

    public function disconnect(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Admin access required to disconnect Google Drive storage.');

        $connection = GoogleDriveConnection::current();
        if ($connection) {
            $connection->delete();
            ActivityLogger::log(
                $request->user(),
                'google_drive.disconnected',
                'Disconnected Google Drive asset storage',
                'google_drive_connection',
                null,
                'Google Drive'
            );
        }

        return ApiResponse::success(null, 'Google Drive disconnected.');
    }

    private function driveCallbackUrl(?string $configuredRedirect): string
    {
        $configured = trim((string) $configuredRedirect);
        if ($configured !== '' && str_contains($configured, '/api/social/callback/google-drive')) {
            return $this->normalizeAbsoluteUrl($configured);
        }

        return $this->backendUrl('/api/social/callback/google-drive');
    }

    private function normalizeAbsoluteUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
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

    private function frontendUrl(): string
    {
        return rtrim(
            $this->normalizeAbsoluteUrl((string) config('app.frontend_url', config('app.url'))),
            '/'
        );
    }

    private function redirectSuccess(): RedirectResponse
    {
        return redirect($this->frontendUrl().'/oauth-apps?connected=google_drive');
    }

    private function redirectError(string $message = 'oauth_failed'): RedirectResponse
    {
        return redirect($this->frontendUrl().'/oauth-apps?error=oauth_failed&message='.urlencode($message));
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
            Log::warning('Google Drive OAuth state decrypt failed', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
