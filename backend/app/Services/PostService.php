<?php

namespace App\Services;

use App\DTOs\PostUpdateData;
use App\Models\Feed;
use App\Models\Post;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PublicFeedCache;

class PostService
{
    public function updatePost(Post $post, Feed $feed, PostUpdateData $dto, User $user): Post
    {
        $updatePayload = array_filter([
            'status' => $dto->status,
            'pinned' => $dto->pinned,
        ], fn ($v) => $v !== null);

        $post->update($updatePayload);

        if ($dto->status !== null || $dto->pinned !== null) {
            $feed->loadMissing('workspace');
            if ($feed->workspace) {
                PublicFeedCache::bump($feed->workspace);
            }
        }

        if ($dto->status !== null) {
            $action = match ($dto->status) {
                'approved' => 'post.approved',
                'rejected' => 'post.rejected',
                default    => 'post.updated',
            };
            ActivityLogger::log($user, $action, ucfirst($dto->status) . " post from \"{$feed->name}\"", 'post', $post->id, $feed->name);
        }

        if ($dto->pinned !== null) {
            $action = $dto->pinned ? 'post.pinned' : 'post.unpinned';
            ActivityLogger::log($user, $action, ($dto->pinned ? 'Pinned' : 'Unpinned') . " post from \"{$feed->name}\"", 'post', $post->id, $feed->name);
        }

        return $post->fresh();
    }

    public function deletePost(Post $post, Feed $feed, User $user): void
    {
        ActivityLogger::log($user, 'post.deleted', "Deleted post from \"{$feed->name}\"", 'post', $post->id, $feed->name);
        $feed->loadMissing('workspace');
        if ($feed->workspace) {
            PublicFeedCache::bump($feed->workspace);
        }
        $post->delete();
    }
}
