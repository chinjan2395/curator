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
use App\Services\AI\AiInsightsService;
use App\Services\AI\AiProviderInterface;
use App\Services\Content\AssetTaggingService;
use App\Services\AI\GroqAiProvider;
use App\Services\AI\OllamaAiProvider;
use App\Services\AI\AiImageProviderInterface;
use App\Services\AI\AiImageGenerationService;
use App\Services\AI\OpenAiImageProvider;
use App\Services\AI\StubAiImageProvider;
use App\Models\GoogleDriveConnection;
use App\Support\ContentPackageMediaResolver;
use App\Support\GoogleDriveConfig;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
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
        $this->app->bind(AiImageProviderInterface::class, function () {
            return match (config('services.ai.image.driver', 'stub')) {
                'openai' => new OpenAiImageProvider,
                default => new StubAiImageProvider,
            };
        });
        $this->app->singleton(AiContentService::class, function ($app) {
            return new AiContentService($app->make(AiProviderInterface::class));
        });
        $this->app->singleton(AiImageGenerationService::class, function ($app) {
            return new AiImageGenerationService(
                $app->make(AiImageProviderInterface::class),
                $app->make(AssetTaggingService::class),
                new ContentPackageMediaResolver,
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
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('embed-analytics', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
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

            $accessToken = $client->fetchAccessTokenWithRefreshToken($config['refreshToken']);
            if (isset($accessToken['error'])) {
                $message = $accessToken['error_description'] ?? $accessToken['error'];
                if (($config['source'] ?? null) === 'database') {
                    GoogleDriveConnection::current()?->markNeedsReauth($message);
                }
                throw new \RuntimeException('Google Drive authentication failed: '.$message);
            }

            if (($config['source'] ?? null) === 'database') {
                $connection = GoogleDriveConnection::current();
                if ($connection) {
                    $connection->access_token = $accessToken['access_token'] ?? null;
                    $expiresIn = (int) ($accessToken['expires_in'] ?? 3600);
                    $connection->expires_at = now()->addSeconds($expiresIn);
                    $connection->markValid();
                }
            }

            $client->setAccessToken($accessToken);

            $service = new GoogleDrive($client);
            $rootFolder = trim((string) ($config['folder'] ?? '/'));
            $adapter = new GoogleDriveAdapter($service, $rootFolder !== '' ? $rootFolder : '/', $options);
            $driver = new Filesystem($adapter);

            return new FilesystemAdapter($driver, $adapter, $config);
        });
    }
}
