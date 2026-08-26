<?php

namespace App\Providers;

use App\Repositories\Contracts\FeedRepositoryInterface;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Repositories\Contracts\SocialCredentialRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Repositories\FeedRepository;
use App\Repositories\PostRepository;
use App\Repositories\SocialCredentialRepository;
use App\Repositories\WorkspaceRepository;
use App\Services\AI\AiContentService;
use App\Services\AI\AiImageGenerationService;
use App\Services\AI\AiInsightsService;
use App\Services\AI\AiProviderInterface;
use App\Services\AI\AiTextProviderFactory;
use App\Services\AI\GroqAiProvider;
use App\Services\AI\Image\AiImageProviderFactory;
use App\Services\AI\Image\ImageKeyResolver;
use App\Services\AI\OllamaAiProvider;
use App\Services\AI\StubAiProvider;
use App\Services\AI\Text\TextKeyResolver;
use App\Services\Content\AssetStorageService;
use App\Services\Content\AssetTaggingService;
use App\Services\Storage\GoogleDriveTokenService;
use App\Support\ContentPackageMediaResolver;
use App\Support\DestructiveDatabaseGuard;
use App\Support\GoogleDriveConfig;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FeedRepositoryInterface::class, FeedRepository::class);
        $this->app->bind(WorkspaceRepositoryInterface::class, WorkspaceRepository::class);
        $this->app->bind(SocialCredentialRepositoryInterface::class, SocialCredentialRepository::class);
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
        $this->app->bind(AiProviderInterface::class, function () {
            return match (config('services.ai.driver', 'stub')) {
                'groq' => new GroqAiProvider,
                'ollama' => new OllamaAiProvider,
                default => new StubAiProvider,
            };
        });
        // Image providers are built per generation, because the API key depends on
        // which user is generating (BYOK) rather than on the environment.
        $this->app->singleton(AiImageProviderFactory::class);
        $this->app->singleton(ImageKeyResolver::class);
        // Text providers are likewise built per generation (BYOK).
        $this->app->singleton(AiTextProviderFactory::class);
        $this->app->singleton(TextKeyResolver::class);
        $this->app->singleton(AiContentService::class, function ($app) {
            return new AiContentService(
                $app->make(AiTextProviderFactory::class),
                $app->make(TextKeyResolver::class),
            );
        });
        $this->app->singleton(AiImageGenerationService::class, function ($app) {
            return new AiImageGenerationService(
                $app->make(AiImageProviderFactory::class),
                $app->make(ImageKeyResolver::class),
                $app->make(AssetTaggingService::class),
                new ContentPackageMediaResolver,
                $app->make(AssetStorageService::class),
            );
        });
        $this->app->singleton(AiInsightsService::class, function ($app) {
            return new AiInsightsService($app->make(AiProviderInterface::class));
        });
        $this->app->singleton(AssetTaggingService::class, function ($app) {
            return new AssetTaggingService($app->make(AiProviderInterface::class));
        });
    }

    public function boot(): void
    {
        // Official Laravel kill-switch for migrate:fresh / db:wipe / migrate:refresh /
        // migrate:reset / migrate:rollback. Works even when CommandStarting is skipped
        // (PHPUnit). Never enable in production — protects Postgres data.
        DB::prohibitDestructiveCommands(! $this->app->environment(['local', 'testing']));

        // Extra guard for production HTTP/CLI Artisan::call (CommandStarting is not
        // dispatched during PHPUnit — see Console Kernel::rerouteSymfonyCommandEvents).
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command === null || $event->command === '') {
                return;
            }

            DestructiveDatabaseGuard::abortIfDestructiveCommandBlocked($event->command);
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('embed-analytics', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('media-proxy', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        VerifyEmail::createUrlUsing(function ($user) {
            $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            $id = $user->getKey();
            $hash = sha1($user->getEmailForVerification());

            return $base.'/verify-email?id='.$id.'&hash='.$hash;
        });

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            if ($base && ! preg_match('#^https?://#i', $base)) {
                $base = 'https://'.$base;
            }

            return $base.'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });

        Storage::extend('google', function ($app, array $config) {
            $config = array_merge($config, GoogleDriveConfig::resolve());

            $options = [];

            if (! empty($config['teamDriveId'] ?? null)) {
                $options['teamDriveId'] = $config['teamDriveId'];
            }

            if (! empty($config['sharedFolderId'] ?? null)) {
                $options['sharedFolderId'] = $config['sharedFolderId'];
            }

            if (! GoogleDriveConfig::isConfigured()) {
                throw new \RuntimeException('Google Drive disk is not configured with valid OAuth credentials.');
            }

            $client = new GoogleClient;
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->setApplicationName(config('app.name', 'Curator'));
            $client->setScopes([GoogleDrive::DRIVE_FILE]);
            $client->setAccessType('offline');

            if (($config['source'] ?? null) === 'database') {
                // Use cached access token when still valid; only hit Google when it is
                // expired or within the 5-minute expiry buffer.
                $tokenService = $app->make(GoogleDriveTokenService::class);
                $rawToken = $tokenService->getValidAccessToken();
                if ($rawToken === null) {
                    throw new \RuntimeException('Google Drive authentication failed: could not obtain a valid access token.');
                }
                $client->setAccessToken(['access_token' => $rawToken, 'token_type' => 'Bearer', 'expires_in' => 3600]);
            } else {
                // Env-only config: no DB record to cache against, always exchange.
                $accessToken = $client->fetchAccessTokenWithRefreshToken($config['refreshToken']);
                if (isset($accessToken['error'])) {
                    $message = $accessToken['error_description'] ?? $accessToken['error'];
                    throw new \RuntimeException('Google Drive authentication failed: '.$message);
                }
                $client->setAccessToken($accessToken);
            }

            $service = new GoogleDrive($client);
            $rootFolder = trim((string) ($config['folder'] ?? '/'));
            $adapter = new GoogleDriveAdapter($service, $rootFolder !== '' ? $rootFolder : '/', $options);
            $driver = new Filesystem($adapter);

            return new FilesystemAdapter($driver, $adapter, $config);
        });
    }
}
