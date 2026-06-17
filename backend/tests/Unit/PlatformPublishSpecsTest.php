<?php

namespace Tests\Unit;

use App\Support\PlatformPublishSpecs;
use PHPUnit\Framework\TestCase;

class PlatformPublishSpecsTest extends TestCase
{
    public function test_all_platforms_have_content_types_and_docs(): void
    {
        $specs = PlatformPublishSpecs::all();

        $this->assertArrayHasKey('twitter', $specs);
        $this->assertArrayHasKey('instagram', $specs);
        $this->assertArrayHasKey('youtube', $specs);

        foreach ($specs as $platform => $spec) {
            $this->assertNotEmpty($spec['summary'], "Missing summary for {$platform}");
            $this->assertNotEmpty($spec['content_types'], "Missing content types for {$platform}");
            $this->assertArrayHasKey('native_publish', $spec);
        }
    }

    public function test_native_matrix_matches_specs(): void
    {
        $matrix = PlatformPublishSpecs::nativeMatrix();

        $this->assertTrue($matrix['twitter']['enabled']);
        $this->assertFalse($matrix['youtube']['enabled']);
        $this->assertStringContainsString('embed', strtolower((string) $matrix['youtube']['reason']));
    }

    public function test_normalize_aliases(): void
    {
        $this->assertSame('twitter', PlatformPublishSpecs::normalize('x'));
        $this->assertSame('instagram', PlatformPublishSpecs::forPlatform('instagram')['native_publish'] ? 'instagram' : '');
    }

    public function test_instagram_requires_media(): void
    {
        $spec = PlatformPublishSpecs::forPlatform('instagram');
        $text = null;
        foreach ($spec['content_types'] as $type) {
            if ($type['id'] === 'text') {
                $text = $type;
                break;
            }
        }

        $this->assertNotNull($text);
        $this->assertFalse($text['supported']);
    }
}
