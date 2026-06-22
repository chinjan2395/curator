<?php

namespace App\Jobs;

use App\Events\FeedSyncUpdated;
use App\Models\Feed;
use App\Services\FeedSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncFeedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public int $feedId,
        public string $triggeredBy = 'queue',
    ) {}

    public function handle(FeedSyncService $syncService): void
    {
        $feed = Feed::query()->with(['socialCredential', 'workspace'])->find($this->feedId);
        if (! $feed instanceof Feed) {
            return;
        }

        $userId = (int) ($feed->workspace?->owner_id ?? 0);
        if ($userId > 0) {
            event(new FeedSyncUpdated($userId, [
                'feed_id' => $feed->id,
                'workspace_id' => $feed->workspace_id,
                'status' => 'started',
                'posts_synced' => 0,
                'triggered_by' => $this->triggeredBy,
            ]));
        }

        $syncService->syncFeed($feed, $this->triggeredBy);
    }
}
