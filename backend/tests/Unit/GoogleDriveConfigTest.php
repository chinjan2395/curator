<?php

namespace Tests\Unit;

use App\Support\GoogleDriveConfig;
use Tests\TestCase;

class GoogleDriveConfigTest extends TestCase
{
    public function test_is_not_configured_when_refresh_token_is_an_access_token(): void
    {
        config([
            'filesystems.disks.googledrive.clientId' => 'client-id',
            'filesystems.disks.googledrive.clientSecret' => 'client-secret',
            'filesystems.disks.googledrive.refreshToken' => 'ya29.access-token-value',
        ]);

        $this->assertFalse(GoogleDriveConfig::isConfigured());
    }

    public function test_is_configured_with_valid_oauth_values(): void
    {
        config([
            'filesystems.disks.googledrive.clientId' => 'client-id',
            'filesystems.disks.googledrive.clientSecret' => 'client-secret',
            'filesystems.disks.googledrive.refreshToken' => '1//refresh-token-value',
        ]);

        $this->assertTrue(GoogleDriveConfig::isConfigured());
    }

    public function test_resolve_falls_back_to_process_env_when_config_is_empty(): void
    {
        config([
            'filesystems.disks.googledrive.clientId' => '',
            'filesystems.disks.googledrive.clientSecret' => '',
            'filesystems.disks.googledrive.refreshToken' => '',
        ]);

        putenv('GOOGLE_DRIVE_CLIENT_ID=client-from-env');
        putenv('GOOGLE_DRIVE_CLIENT_SECRET=secret-from-env');
        putenv('GOOGLE_DRIVE_REFRESH_TOKEN=1//refresh-from-env');

        try {
            $resolved = GoogleDriveConfig::resolve();

            $this->assertSame('client-from-env', $resolved['clientId']);
            $this->assertSame('secret-from-env', $resolved['clientSecret']);
            $this->assertSame('1//refresh-from-env', $resolved['refreshToken']);
            $this->assertTrue(GoogleDriveConfig::isConfigured());
        } finally {
            putenv('GOOGLE_DRIVE_CLIENT_ID');
            putenv('GOOGLE_DRIVE_CLIENT_SECRET');
            putenv('GOOGLE_DRIVE_REFRESH_TOKEN');
        }
    }
}
