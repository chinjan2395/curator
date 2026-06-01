<?php

namespace App\Console\Commands;

use App\Models\ScheduledPost;
use App\Services\Social\SocialPublisherService;
use Illuminate\Console\Command;

class PublishScheduledPostsCommand extends Command
{
    protected $signature = 'social:publish-scheduled';

    protected $description = 'Publish due scheduled posts to social platforms';

    public function handle(SocialPublisherService $publisher): int
    {
        $due = ScheduledPost::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        foreach ($due as $post) {
            $publisher->publish($post);
        }

        $this->info("Processed {$due->count()} scheduled posts.");

        return self::SUCCESS;
    }
}
