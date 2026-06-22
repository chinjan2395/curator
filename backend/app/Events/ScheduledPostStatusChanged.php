<?php

namespace App\Events;

use App\Models\ScheduledPost;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduledPostStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param  array<string, mixed>  $post */
    public function __construct(
        public int $userId,
        public array $post,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'scheduled-post.status-changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'post' => $this->post,
        ];
    }

    public static function fromModel(ScheduledPost $post): self
    {
        $post->loadMissing(['socialCredential:id,provider,account_label', 'contentPackage:id,caption,platform']);

        return new self((int) $post->user_id, $post->toArray());
    }
}
