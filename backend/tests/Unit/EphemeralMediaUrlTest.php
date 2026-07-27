<?php

namespace Tests\Unit;

use App\Support\EphemeralMediaUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EphemeralMediaUrlTest extends TestCase
{
    #[Test]
    public function it_detects_instagram_cdn_hosts(): void
    {
        $url = 'https://scontent-iad3-1.cdninstagram.com/v/t51.82787-15/photo.jpg?oe=6A66A4F8&oh=abc';

        $this->assertTrue(EphemeralMediaUrl::needsProxy($url));
    }

    #[Test]
    public function it_leaves_youtube_thumbs_alone(): void
    {
        $this->assertFalse(EphemeralMediaUrl::needsProxy('https://i.ytimg.com/vi/abc123/hqdefault.jpg'));
    }

    #[Test]
    public function it_detects_expired_oe_timestamp(): void
    {
        $expired = 'https://scontent.cdninstagram.com/v/t51/x.jpg?oe='.dechex(time() - 3600);
        $fresh = 'https://scontent.cdninstagram.com/v/t51/x.jpg?oe='.dechex(time() + 3600);

        $this->assertTrue(EphemeralMediaUrl::isExpiredOrExpiringSoon($expired));
        $this->assertFalse(EphemeralMediaUrl::isExpiredOrExpiringSoon($fresh));
    }
}
