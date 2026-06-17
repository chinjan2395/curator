<?php

namespace Tests\Feature;

use App\Models\GoogleDriveConnection;
use App\Models\OAuthAppConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleDriveConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_reports_disconnected_by_default(): void
    {
        config([
            'filesystems.disks.googledrive.clientId' => '',
            'filesystems.disks.googledrive.clientSecret' => '',
            'filesystems.disks.googledrive.refreshToken' => '',
            'services.google.client_id' => '',
            'services.google.client_secret' => '',
        ]);

        putenv('GOOGLE_DRIVE_CLIENT_ID=');
        putenv('GOOGLE_DRIVE_CLIENT_SECRET=');
        putenv('GOOGLE_DRIVE_REFRESH_TOKEN=');
        putenv('GOOGLE_CLIENT_ID=');
        putenv('GOOGLE_CLIENT_SECRET=');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/google-drive')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.oauth_ready', false);
    }

    public function test_non_admin_cannot_start_connect_flow(): void
    {
        $user = User::factory()->create();
        $this->seedGoogleOAuthConfig();

        $this->actingAs($user)
            ->postJson('/api/google-drive/connect')
            ->assertForbidden();
    }

    public function test_admin_can_start_connect_flow_when_google_oauth_configured(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->seedGoogleOAuthConfig();

        $response = $this->actingAs($admin)->postJson('/api/google-drive/connect');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['auth_url']]);

        $authUrl = $response->json('data.auth_url');
        $this->assertIsString($authUrl);
        $this->assertStringContainsString('accounts.google.com', $authUrl);
        $this->assertStringContainsString('drive.file', urldecode($authUrl));
    }

    public function test_admin_connect_requires_google_oauth_config(): void
    {
        config([
            'filesystems.disks.googledrive.clientId' => '',
            'filesystems.disks.googledrive.clientSecret' => '',
            'services.google.client_id' => '',
            'services.google.client_secret' => '',
        ]);
        putenv('GOOGLE_CLIENT_ID=');
        putenv('GOOGLE_CLIENT_SECRET=');
        putenv('GOOGLE_DRIVE_CLIENT_ID=');
        putenv('GOOGLE_DRIVE_CLIENT_SECRET=');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/google-drive/connect')
            ->assertStatus(503);
    }

    public function test_status_reports_database_connection(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        GoogleDriveConnection::query()->create([
            'connected_by_user_id' => $admin->id,
            'account_email' => 'drive@example.com',
            'account_label' => 'Drive Admin',
            'refresh_token' => '1//refresh-token-value',
            'token_health' => 'valid',
            'connected_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/google-drive')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.account_email', 'drive@example.com')
            ->assertJsonPath('data.source', 'database');
    }

    public function test_admin_can_disconnect_google_drive(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        GoogleDriveConnection::query()->create([
            'connected_by_user_id' => $admin->id,
            'account_email' => 'drive@example.com',
            'refresh_token' => '1//refresh-token-value',
            'token_health' => 'valid',
            'connected_at' => now(),
        ]);

        $this->actingAs($admin)
            ->deleteJson('/api/google-drive')
            ->assertOk();

        $this->assertDatabaseCount('google_drive_connections', 0);
    }

    public function test_refresh_command_updates_connection_health(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->seedGoogleOAuthConfig();

        GoogleDriveConnection::query()->create([
            'connected_by_user_id' => $admin->id,
            'account_email' => 'drive@example.com',
            'refresh_token' => '1//refresh-token-value',
            'token_health' => 'needs_reauth',
            'connected_at' => now(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $this->artisan('google-drive:refresh-token')->assertSuccessful();

        $connection = GoogleDriveConnection::current();
        $this->assertSame('valid', $connection->token_health);
        $this->assertSame('new-access-token', $connection->access_token);
    }

    private function seedGoogleOAuthConfig(): void
    {
        OAuthAppConfig::query()->create([
            'scope' => OAuthAppConfig::SCOPE_SHARED,
            'user_id' => null,
            'provider' => 'google',
            'client_id' => 'shared-google-id',
            'client_secret' => 'shared-google-secret',
            'redirect_uri' => 'https://example.com/api/social/callback/google-drive',
        ]);
    }
}
