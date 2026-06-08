<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Support\AssetUrl;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetUrlTest extends TestCase
{
    public function test_preview_returns_google_drive_url_for_drive_assets(): void
    {
        Storage::partialMock()
            ->shouldReceive('disk')
            ->with('googledrive')
            ->andReturnSelf()
            ->shouldReceive('exists')
            ->with('assets/1/photo.jpg')
            ->andReturn(true)
            ->shouldReceive('url')
            ->with('assets/1/photo.jpg')
            ->andReturn('https://drive.google.com/uc?id=abc123&export=media');

        $asset = new Asset([
            'id' => 1,
            'storage_path' => 'assets/1/photo.jpg',
            'storage_disk' => 'googledrive',
        ]);

        $this->assertSame(
            'https://drive.google.com/thumbnail?id=abc123&sz=w256',
            AssetUrl::preview($asset),
        );
    }

    public function test_preview_returns_signed_route_for_public_disk_assets(): void
    {
        $asset = new Asset([
            'storage_path' => 'assets/1/photo.jpg',
            'storage_disk' => 'public',
        ]);
        $asset->id = 42;

        $url = AssetUrl::preview($asset);

        $this->assertIsString($url);
        $this->assertStringContainsString('/api/content/assets/42/file', $url);
        $this->assertStringContainsString('signature=', $url);
    }
}
