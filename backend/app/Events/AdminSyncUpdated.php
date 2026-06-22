<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminSyncUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  'run_all'|'resync_credential'|'sync_feed'  $jobType
     * @param  'started'|'progress'|'completed'|'failed'  $status
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public int $adminUserId,
        public string $jobType,
        public string $status,
        public ?array $data = null,
        public ?string $message = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.'.$this->adminUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'admin-sync.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'job_type' => $this->jobType,
            'status' => $this->status,
            'data' => $this->data,
            'message' => $this->message,
        ];
    }
}
