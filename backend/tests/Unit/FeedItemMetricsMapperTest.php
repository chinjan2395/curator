<?php

namespace Tests\Unit;

use App\Support\FeedItemMetricsMapper;
use PHPUnit\Framework\TestCase;

class FeedItemMetricsMapperTest extends TestCase
{
    public function test_maps_youtube_statistics(): void
    {
        $mapped = FeedItemMetricsMapper::fromYouTube([
            'viewCount' => '100',
            'likeCount' => '10',
            'commentCount' => '2',
        ], 'abc123');

        $this->assertSame(100, $mapped['views']);
        $this->assertSame(10, $mapped['likes']);
        $this->assertSame(2, $mapped['comments']);
        $this->assertStringContainsString('abc123', $mapped['post_url']);
    }

    public function test_maps_twitter_public_metrics(): void
    {
        $mapped = FeedItemMetricsMapper::fromTwitter([
            'like_count' => 5,
            'reply_count' => 1,
            'retweet_count' => 2,
            'impression_count' => 50,
        ], 'tweet1');

        $this->assertSame(5, $mapped['likes']);
        $this->assertSame(1, $mapped['comments']);
        $this->assertSame(2, $mapped['shares']);
    }
}
