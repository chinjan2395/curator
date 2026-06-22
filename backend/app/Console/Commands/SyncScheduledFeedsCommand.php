<?php

namespace App\Console\Commands;

use App\Jobs\SyncFeedJob;
use App\Models\Feed;
use Illuminate\Console\Command;

class SyncScheduledFeedsCommand extends Command
{
    protected $signature = 'feeds:sync-scheduled';

    protected $description = 'Queue sync jobs for all feeds on the scheduler interval';

    public function handle(): int
    {
        $queued = 0;

        Feed::query()
            ->with(['socialCredential', 'workspace'])
            ->chunkById(50, function ($feeds) use (&$queued): void {
                foreach ($feeds as $feed) {
                    SyncFeedJob::dispatch($feed->id, 'scheduler');
                    $queued++;
                }
            });

        $this->info("Queued scheduled sync for {$queued} feed(s).");

        return self::SUCCESS;
    }
}
