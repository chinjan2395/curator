<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;
use Illuminate\Support\Str;

class StubPublisher implements PublisherInterface
{
    public function publish(ScheduledPost $scheduledPost): array
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Native social publishing is not configured for this provider yet.');
        }

        return [
            'platform_post_id' => 'stub_'.Str::random(12),
            'platform_post_url' => 'https://example.com/posts/'.Str::random(8),
        ];
    }
}
