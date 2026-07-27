<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Media\MediaProxyService;
use App\Support\EphemeralMediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CachePostThumbnailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $postId,
    ) {}

    public function handle(MediaProxyService $mediaProxy): void
    {
        $post = Post::query()->find($this->postId);
        if (! $post instanceof Post) {
            return;
        }

        if (! EphemeralMediaUrl::needsProxy($post->thumbnail_url)) {
            return;
        }

        $mediaProxy->ensurePostThumbnailCached($post);
    }
}
