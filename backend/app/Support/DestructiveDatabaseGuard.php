<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Blocks wipe-style DB operations outside local/testing so production Postgres
 * (and any non-local DB) cannot be destroyed via DevTools, Artisan, or tests.
 */
class DestructiveDatabaseGuard
{
    /** @var list<string> */
    public const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    /** Only local MySQL schema PHPUnit may RefreshDatabase against. */
    public const TESTING_MYSQL_DATABASE = 'curator_testing';

    public static function allowsDestructiveCommands(?Application $app = null): bool
    {
        $app ??= app();

        // Never allow in production. Also deny staging/etc. — opt-in only via local/testing.
        return $app->environment(['local', 'testing']);
    }

    public static function isDestructiveCommand(string $command): bool
    {
        $name = strtolower(trim(explode(' ', $command, 2)[0]));

        return in_array($name, self::DESTRUCTIVE_COMMANDS, true);
    }

    public static function abortIfDestructiveCommandBlocked(string $command): void
    {
        if (! self::isDestructiveCommand($command)) {
            return;
        }

        if (self::allowsDestructiveCommands()) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run [{$command}]: destructive database commands are disabled outside local/testing to protect production data."
        );
    }

    /**
     * Hard stop if the test suite resolved anything other than the isolated local test DB.
     * Production Postgres uses pgsql + DB_URL — both are rejected here.
     */
    public static function assertSafeTestingDatabase(): void
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}", []);
        $database = (string) ($config['database'] ?? '');
        $url = (string) ($config['url'] ?? '');

        if ($connection === 'pgsql' || str_starts_with(strtolower($url), 'pgsql:') || str_contains(strtolower($url), 'postgres')) {
            throw new RuntimeException(
                'Refusing to run tests against PostgreSQL. PHPUnit must use the isolated MySQL schema "'
                .self::TESTING_MYSQL_DATABASE.'" — never production Postgres.'
            );
        }

        if ($connection !== 'mysql') {
            throw new RuntimeException(
                "Refusing to run tests: expected mysql connection, got [{$connection}]."
            );
        }

        if ($database !== self::TESTING_MYSQL_DATABASE) {
            throw new RuntimeException(
                'Refusing to run tests: expected database "'.self::TESTING_MYSQL_DATABASE."\", got [{$database}]."
            );
        }

        if ($url !== '') {
            throw new RuntimeException(
                'Refusing to run tests: DB_URL must be empty for PHPUnit (got a non-empty URL).'
            );
        }
    }
}
