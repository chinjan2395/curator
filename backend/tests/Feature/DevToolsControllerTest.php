<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DevToolsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_commands_list_includes_migrate_fresh_with_danger_metadata(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->getJson('/api/admin/dev-tools/commands');

        $response->assertOk();

        $commands = collect($response->json('data'));
        $migrateFresh = $commands->firstWhere('id', 'migrate:fresh');

        $this->assertNotNull($migrateFresh);
        $this->assertTrue($migrateFresh['danger']);
        $this->assertStringContainsString('DESTRUCTIVE', $migrateFresh['warning']);
        $this->assertSame('php artisan migrate:fresh', $migrateFresh['command']);
    }

    public function test_migrate_fresh_is_hidden_and_rejected_in_production(): void
    {
        $this->app['env'] = 'production';

        Sanctum::actingAs(User::factory()->admin()->create());

        $commands = collect($this->getJson('/api/admin/dev-tools/commands')->json('data'));
        $this->assertNull($commands->firstWhere('id', 'migrate:fresh'));

        $this->postJson('/api/admin/dev-tools/run', ['command' => 'migrate:fresh'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['command']);
    }

    public function test_non_admin_cannot_access_dev_tools_commands(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/dev-tools/commands')->assertForbidden();
    }

    public function test_non_admin_cannot_run_dev_tools_command(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/admin/dev-tools/run', ['command' => 'migrate:fresh'])
            ->assertForbidden();
    }

    public function test_admin_can_run_migrate_fresh_with_force_flag(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', ['--force' => true])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Migration table created successfully.');

        $response = $this->postJson('/api/admin/dev-tools/run', ['command' => 'migrate:fresh']);

        $response->assertOk()
            ->assertJsonPath('data.command', 'php artisan migrate:fresh')
            ->assertJsonPath('data.exit_code', 0);
    }

    public function test_run_rejects_unknown_command(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/admin/dev-tools/run', ['command' => 'db:wipe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['command']);
    }
}
