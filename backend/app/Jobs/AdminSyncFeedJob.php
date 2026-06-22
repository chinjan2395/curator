<?php

namespace App\Jobs;

use App\Events\AdminSyncUpdated;
use App\Models\Feed;
use App\Services\FeedSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AdminSyncFeedJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public int $adminUserId,
        public int $feedId,
    ) {}

    public function handle(FeedSyncService $syncService): void
    {
        $feed = Feed::query()->with(['socialCredential', 'workspace'])->find($this->feedId);
        if (! $feed) {
            return;
        }

        event(new AdminSyncUpdated(
            $this->adminUserId,
            'sync_feed',
            'started',
            ['feed_id' => $feed->id],
            "Syncing {$feed->name}…",
        ));

        try {
            $result = $syncService->syncFeed($feed, 'admin');

            event(new AdminSyncUpdated(
                $this->adminUserId,
                'sync_feed',
                $result !== null ? 'completed' : 'failed',
                ['feed_id' => $feed->id, 'synced' => $result !== null],
                $result !== null ? 'Feed synced.' : 'Credential expired or revoked.',
            ));
        } catch (Throwable $e) {
            event(new AdminSyncUpdated(
                $this->adminUserId,
                'sync_feed',
                'failed',
                ['feed_id' => $feed->id],
                $e->getMessage(),
            ));

            throw $e;
        }
    }
}
