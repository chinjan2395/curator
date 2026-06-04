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
use App\Services\AI\StubAiProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

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
        $this->app->singleton(AiContentService::class, function ($app) {
            return new AiContentService($app->make(AiProviderInterface::class));
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
    }
}
