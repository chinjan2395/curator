<?php

namespace Tests\Unit;

use App\Services\Content\AssetStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetStorageServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_disk_defaults_to_public_when_google_drive_not_configured(): void
    {
        config([
            'filesystems.disks.googledrive.clientId' => '',
            'filesystems.disks.googledrive.clientSecret' => '',
            'filesystems.disks.googledrive.refreshToken' => '',
        ]);

        $service = new AssetStorageService;

        $this->assertSame('public', $service->disk());
    }

    public function test_disk_uses_google_drive_when_configured(): void
    {
        config([
            'filesystems.disks.googledrive.clientId' => 'client-id',
            'filesystems.disks.googledrive.clientSecret' => 'client-secret',
            'filesystems.disks.googledrive.refreshToken' => '1//refresh-token-value',
        ]);

        $service = new AssetStorageService;

        $this->assertSame('googledrive', $service->disk());
    }
}
