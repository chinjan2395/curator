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
}
