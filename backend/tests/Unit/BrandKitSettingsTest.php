<?php

namespace Tests\Unit;

use App\Support\BrandKitSettings;
use PHPUnit\Framework\TestCase;

class BrandKitSettingsTest extends TestCase
{
    public function test_normalizes_colors_and_rejects_invalid_hex(): void
    {
        $result = BrandKitSettings::normalize(
            ['primary' => 'not-a-color', 'secondary' => '#abc'],
            null,
            null,
        );

        $this->assertSame('#2563eb', $result['colors']['primary']);
        $this->assertSame('#aabbcc', $result['colors']['secondary']);
    }

    public function test_sanitizes_watermark_url_and_opacity(): void
    {
        $result = BrandKitSettings::normalize(
            null,
            null,
            ['enabled' => true, 'url' => 'ftp://bad.test/x.png', 'opacity' => 9],
        );

        $this->assertTrue($result['watermark']['enabled']);
        $this->assertSame('', $result['watermark']['url']);
        $this->assertSame(1.0, $result['watermark']['opacity']);
    }

    public function test_sanitize_logo_url_accepts_https_only(): void
    {
        $this->assertNull(BrandKitSettings::sanitizeLogoUrl('javascript:alert(1)'));
        $this->assertSame(
            'https://cdn.example.com/logo.png',
            BrandKitSettings::sanitizeLogoUrl('https://cdn.example.com/logo.png'),
        );
    }
}
