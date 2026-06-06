<?php

namespace Tests\Unit;

use App\Support\GoogleDriveUrl;
use PHPUnit\Framework\TestCase;

class GoogleDriveUrlTest extends TestCase
{
    public function test_extracts_file_id_from_uc_url(): void
    {
        $url = 'https://drive.google.com/uc?id=1szvTgQQXKHtJ6PjjWgeYzkel9B6sewO0&export=media';

        $this->assertSame('1szvTgQQXKHtJ6PjjWgeYzkel9B6sewO0', GoogleDriveUrl::extractFileId($url));
    }

    public function test_converts_uc_url_to_thumbnail_url(): void
    {
        $url = 'https://drive.google.com/uc?id=abc123&export=media';

        $this->assertSame(
            'https://drive.google.com/thumbnail?id=abc123&sz=w256',
            GoogleDriveUrl::toThumbnailUrl($url),
        );
    }
}
