<?php

namespace App\Jobs;

use App\Models\Feed;
use App\Services\Media\MediaProxyService;
use App\Support\EphemeralMediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CacheFeedAvatarJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $feedId,
    ) {}

    public function handle(MediaProxyService $mediaProxy): void
    {
        $feed = Feed::query()->find($this->feedId);
        if (! $feed instanceof Feed) {
            return;
        }

        if (! EphemeralMediaUrl::needsProxy($feed->account_avatar_url)) {
            return;
        }

        $mediaProxy->ensureFeedAvatarCached($feed);
    }
}
