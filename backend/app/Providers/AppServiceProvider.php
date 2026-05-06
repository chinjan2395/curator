<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

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
