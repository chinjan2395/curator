<?php

namespace Tests\Unit;

use App\Services\Social\Support\MediaUrlClassifier;
use PHPUnit\Framework\TestCase;

class MediaUrlClassifierTest extends TestCase
{
    public function test_detects_video_and_image_extensions(): void
    {
        $this->assertTrue(MediaUrlClassifier::isVideo('https://cdn.example.com/promo.mp4'));
        $this->assertTrue(MediaUrlClassifier::looksLikeImageAsset('https://cdn.example.com/photo.jpg'));
        $this->assertFalse(MediaUrlClassifier::looksLikeImageAsset('https://cdn.example.com/promo.mp4'));
    }
}
