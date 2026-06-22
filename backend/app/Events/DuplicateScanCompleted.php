<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuplicateScanCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public int $workspaceId,
        public int $groupCount,
        public string $triggeredBy = 'scan',
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'duplicate-scan.completed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'group_count' => $this->groupCount,
            'triggered_by' => $this->triggeredBy,
        ];
    }
}
