<?php

namespace App\Jobs;

use App\Events\AdminSyncUpdated;
use App\Models\Feed;
use App\Models\SocialCredential;
use App\Services\FeedSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AdminResyncCredentialJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public int $adminUserId,
        public int $credentialId,
    ) {}

    public function handle(FeedSyncService $syncService): void
    {
        $credential = SocialCredential::query()->find($this->credentialId);
        if (! $credential) {
            return;
        }

        $feeds = Feed::query()
            ->where('social_credential_id', $credential->id)
            ->with(['socialCredential', 'workspace'])
            ->get();

        $total = $feeds->count();

        event(new AdminSyncUpdated(
            $this->adminUserId,
            'resync_credential',
            'started',
            ['credential_id' => $credential->id, 'total' => $total],
            'Re-syncing credential feeds…',
        ));

        try {
            $credential->update(['status' => 'active']);
            $synced = 0;

            foreach ($feeds as $index => $feed) {
                if ($syncService->syncFeed($feed, 'admin') !== null) {
                    $synced++;
                }

                event(new AdminSyncUpdated(
                    $this->adminUserId,
                    'resync_credential',
                    'progress',
                    [
                        'credential_id' => $credential->id,
                        'done' => $index + 1,
                        'total' => $total,
                        'synced' => $synced,
                    ],
                    "Synced {$index + 1} of {$total} feeds…",
                ));
            }

            event(new AdminSyncUpdated(
                $this->adminUserId,
                'resync_credential',
                'completed',
                [
                    'credential_id' => $credential->id,
                    'status' => $credential->fresh()->status,
                    'synced' => $synced,
                    'total' => $total,
                ],
                "Credential re-sync complete — {$synced} of {$total} feeds updated.",
            ));
        } catch (Throwable $e) {
            event(new AdminSyncUpdated(
                $this->adminUserId,
                'resync_credential',
                'failed',
                ['credential_id' => $credential->id],
                $e->getMessage(),
            ));

            throw $e;
        }
    }
}
