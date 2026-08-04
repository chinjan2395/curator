<?php

namespace Tests\Unit;

use App\Support\DestructiveDatabaseGuard;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DestructiveDatabaseGuardTest extends TestCase
{
    public function test_allows_destructive_commands_in_testing(): void
    {
        $this->assertTrue(DestructiveDatabaseGuard::allowsDestructiveCommands());
    }

    public function test_blocks_destructive_commands_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->assertFalse(DestructiveDatabaseGuard::allowsDestructiveCommands());
    }

    public function test_identifies_destructive_commands(): void
    {
        $this->assertTrue(DestructiveDatabaseGuard::isDestructiveCommand('migrate:fresh'));
        $this->assertTrue(DestructiveDatabaseGuard::isDestructiveCommand('db:wipe'));
        $this->assertTrue(DestructiveDatabaseGuard::isDestructiveCommand('migrate:refresh'));
        $this->assertTrue(DestructiveDatabaseGuard::isDestructiveCommand('migrate:reset'));
        $this->assertFalse(DestructiveDatabaseGuard::isDestructiveCommand('migrate'));
        $this->assertFalse(DestructiveDatabaseGuard::isDestructiveCommand('migrate:status'));
    }

    public function test_artisan_migrate_fresh_fails_when_prohibited(): void
    {
        // Simulates production boot: DB::prohibitDestructiveCommands(true).
        // (CommandStarting is intentionally not dispatched during PHPUnit.)
        DB::prohibitDestructiveCommands(true);

        try {
            $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);
            $this->assertNotSame(0, $exitCode);
            $this->assertStringContainsString('prohibited', Artisan::output());
        } finally {
            DB::prohibitDestructiveCommands(false);
            FreshCommand::prohibit(false);
        }
    }

    public function test_abort_helper_throws_outside_local_testing(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('destructive database commands are disabled');

        DestructiveDatabaseGuard::abortIfDestructiveCommandBlocked('migrate:fresh');
    }

    public function test_assert_safe_testing_database_passes_for_curator_testing(): void
    {
        DestructiveDatabaseGuard::assertSafeTestingDatabase();
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('curator_testing', config('database.connections.mysql.database'));
    }

    public function test_assert_safe_testing_database_rejects_pgsql(): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => 'postgres',
            'database.connections.pgsql.url' => 'pgsql://example/postgres',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PostgreSQL');

        DestructiveDatabaseGuard::assertSafeTestingDatabase();
    }
}
