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
            ->with(['socialCredential:id,provider', 'contentPackage:id,caption'])
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No due scheduled posts.');

            return self::SUCCESS;
        }

        $published = 0;
        $retried = 0;
        $failed = 0;

        foreach ($due as $post) {
            $beforeStatus = $post->status;
            $beforeRetry = $post->retry_count;

            $publisher->publish($post);
            $post->refresh();

            $provider = $post->socialCredential?->provider ?? 'unknown';
            $label = "#{$post->id} ({$provider})";

            if ($post->status === 'published') {
                $published++;
                $this->info("Published {$label}");
            } elseif ($post->status === 'failed') {
                $failed++;
                $this->error("Failed {$label}: {$post->error_message}");
            } elseif ($post->status === 'scheduled' && $post->retry_count > $beforeRetry) {
                $retried++;
                $this->warn("Retry queued {$label} at {$post->scheduled_at->toIso8601String()}: {$post->error_message}");
            } else {
                $this->line("No change {$label} (status={$beforeStatus})");
            }
        }

        $this->info("Done: {$published} published, {$retried} queued for retry, {$failed} failed.");

        return self::SUCCESS;
    }
}
