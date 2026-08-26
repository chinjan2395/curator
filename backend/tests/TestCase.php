<?php

namespace Tests;

use App\Support\DestructiveDatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pin tests to the isolated `curator_testing` MySQL schema and refuse Postgres.
     *
     * Docker Compose injects DB_* into $_SERVER; Laravel Env reads $_SERVER before
     * $_ENV. Without this override, RefreshDatabase wiped the live local DB.
     * Production uses Postgres — this harness must never resolve to pgsql/DB_URL.
     */
    public function createApplication()
    {
        $this->refuseIfOriginalEnvironmentIsProduction();
        $this->forceTestingDatabaseEnvironment();

        $app = parent::createApplication();

        DestructiveDatabaseGuard::assertSafeTestingDatabase();

        return $app;
    }

    protected function refuseIfOriginalEnvironmentIsProduction(): void
    {
        $appEnv = (string) (
            $_SERVER['APP_ENV']
            ?? $_ENV['APP_ENV']
            ?? getenv('APP_ENV')
            ?: ''
        );

        if (strtolower($appEnv) === 'production') {
            throw new RuntimeException(
                'Refusing to boot the test suite while APP_ENV=production (protects production Postgres).'
            );
        }
    }

    protected function forceTestingDatabaseEnvironment(): void
    {
        $variables = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => 'mysql',
            'DB_PORT' => '3306',
            'DB_DATABASE' => DestructiveDatabaseGuard::TESTING_MYSQL_DATABASE,
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => 'root',
            // Critical: production Postgres is often configured solely via DB_URL / DATABASE_URL.
            'DB_URL' => '',
            'DATABASE_URL' => '',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'MAIL_MAILER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            // A developer .env with real Drive credentials otherwise makes
            // AssetStorageService pick the googledrive disk and hit the live API
            // mid-test. Tests that exercise Drive set the config explicitly instead.
            'GOOGLE_DRIVE_CLIENT_ID' => '',
            'GOOGLE_DRIVE_CLIENT_SECRET' => '',
            'GOOGLE_DRIVE_REFRESH_TOKEN' => '',
            'GOOGLE_DRIVE_FOLDER' => '',
            'GOOGLE_DRIVE_SHARED_FOLDER_ID' => '',
        ];

        foreach ($variables as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        // Drop any Env repository created by `php artisan test` booting Laravel
        // with live Docker/production credentials before PHPUnit could override them.
        Env::enablePutenv();
    }
}
