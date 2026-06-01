<?php

namespace App\Services\Social\Publishers;

use App\Models\ScheduledPost;

interface PublisherInterface
{
    /** @return array{platform_post_id?: string, platform_post_url?: string} */
    public function publish(ScheduledPost $scheduledPost): array;
}
