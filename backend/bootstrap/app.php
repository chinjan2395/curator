<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
        ]);

        // Trust the platform's reverse proxy (Render/Docker) so Request::isSecure()
        // and route(..., absolute: true) reflect the real https scheme instead of
        // the plain-http scheme the app receives internally. Without this, proxy
        // URLs (e.g. media thumbnails) are generated as http:// and immediately
        // 301-redirected to https:// by the edge on every request.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withSchedule(function (Schedule $schedule) {
        // Dispatch to queue when a worker is available; scheduler still runs inline as fallback.
        $schedule->command('feeds:sync-scheduled')
            ->everyFifteenMinutes()
            ->name('sync-all-feeds')
            ->withoutOverlapping();

        $schedule->command('social:refresh-tokens')
            ->cron('*/45 * * * *')
            ->withoutOverlapping();

        $schedule->command('google-drive:refresh-token')
            ->cron('*/45 * * * *')
            ->withoutOverlapping();

        $schedule->command('social:sync-metadata')
            ->dailyAt('03:00')
            ->withoutOverlapping();

        $schedule->command('social:publish-scheduled')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('ai:scrape-trends')
            ->hourly()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
