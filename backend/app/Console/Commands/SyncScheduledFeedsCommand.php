<?php

namespace App\Console\Commands;

use App\Models\Feed;
use App\Services\FeedSyncService;
use Illuminate\Console\Command;

class SyncScheduledFeedsCommand extends Command
{
    protected $signature = 'feeds:sync-scheduled';

    protected $description = 'Sync all feeds on the scheduler interval (runs inline, no queue worker required)';

    public function handle(FeedSyncService $syncService): int
    {
        $synced = 0;

        Feed::query()
            ->with(['socialCredential', 'workspace'])
            ->chunkById(50, function ($feeds) use ($syncService, &$synced): void {
                foreach ($feeds as $feed) {
                    $syncService->syncFeed($feed, 'scheduler');
                    $synced++;
                }
            });

        $this->info("Scheduled sync finished for {$synced} feed(s).");

        return self::SUCCESS;
    }
}
