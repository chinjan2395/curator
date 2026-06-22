<?php

namespace App\Jobs;

use App\Events\DuplicateScanCompleted;
use App\Models\PostDuplicateGroup;
use App\Models\Workspace;
use App\Services\DuplicateDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DuplicateScanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public int $workspaceId,
        public int $userId,
        public string $triggeredBy = 'scan',
    ) {}

    public function handle(DuplicateDetectionService $detector): void
    {
        $workspace = Workspace::query()->find($this->workspaceId);
        if (! $workspace || (int) $workspace->owner_id !== $this->userId) {
            return;
        }

        $detector->detectForWorkspace($workspace);

        $groupCount = PostDuplicateGroup::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'open')
            ->count();

        event(new DuplicateScanCompleted(
            $this->userId,
            $workspace->id,
            $groupCount,
            $this->triggeredBy,
        ));
    }
}
