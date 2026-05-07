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
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FeedRepositoryInterface::class, FeedRepository::class);
        $this->app->bind(WorkspaceRepositoryInterface::class, WorkspaceRepository::class);
        $this->app->bind(SocialCredentialRepositoryInterface::class, SocialCredentialRepository::class);
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            if ($base && ! preg_match('#^https?://#i', $base)) {
                $base = 'https://'.$base;
            }

            return $base.'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });
    }
}
