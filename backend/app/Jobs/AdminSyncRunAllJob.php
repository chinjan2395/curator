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

class AdminSyncRunAllJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $adminUserId) {}

    public function handle(FeedSyncService $syncService): void
    {
        $total = Feed::query()->count();

        event(new AdminSyncUpdated(
            $this->adminUserId,
            'run_all',
            'started',
            ['total' => $total],
            'Syncing all feeds…',
        ));

        try {
            $done = 0;
            $synced = 0;

            Feed::query()
                ->with(['socialCredential', 'workspace'])
                ->chunkById(50, function ($feeds) use ($syncService, $total, &$done, &$synced): void {
                    foreach ($feeds as $feed) {
                        if ($syncService->syncFeed($feed, 'admin') !== null) {
                            $synced++;
                        }
                        $done++;

                        event(new AdminSyncUpdated(
                            $this->adminUserId,
                            'run_all',
                            'progress',
                            ['done' => $done, 'total' => $total, 'synced' => $synced],
                            "Synced {$done} of {$total} feeds…",
                        ));
                    }
                });

            event(new AdminSyncUpdated(
                $this->adminUserId,
                'run_all',
                'completed',
                ['synced' => $synced, 'total' => $total],
                "Sync complete — {$synced} of {$total} feeds updated.",
            ));
        } catch (Throwable $e) {
            event(new AdminSyncUpdated(
                $this->adminUserId,
                'run_all',
                'failed',
                null,
                $e->getMessage(),
            ));

            throw $e;
        }
    }
}
