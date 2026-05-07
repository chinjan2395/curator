<?php

namespace App\Repositories;

use App\Models\Post;
use App\Models\Workspace;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Support\Carbon;

class PostRepository implements PostRepositoryInterface
{
    public function publishApprovedForWorkspace(Workspace $workspace, Carbon $now): int
    {
        return Post::query()
            ->whereIn('feed_id', $workspace->feeds()->select('id'))
            ->where('status', 'approved')
            ->whereNull('published_at')
            ->update(['published_at' => $now]);
    }

    public function countApprovedForWorkspace(Workspace $workspace): int
    {
        return Post::query()
            ->whereIn('feed_id', $workspace->feeds()->select('id'))
            ->where('status', 'approved')
            ->count();
    }

    public function countPublishedForWorkspace(Workspace $workspace): int
    {
        return Post::query()
            ->whereIn('feed_id', $workspace->feeds()->select('id'))
            ->whereNotNull('published_at')
            ->count();
    }

    public function countPendingForWorkspace(Workspace $workspace): int
    {
        return Post::query()
            ->whereIn('feed_id', $workspace->feeds()->select('id'))
            ->where('status', 'pending')
            ->count();
    }
}
