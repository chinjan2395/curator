<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);
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
