<?php

namespace App\Http\Controllers;

use App\DTOs\PostUpdateData;
use App\Http\Requests\BulkUpdatePostsRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\PostResource;
use App\Models\Feed;
use App\Models\Post;
use App\Models\Workspace;
use App\Services\PostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private readonly PostService $postService) {}

    public function workspaceIndex(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $feedId = $request->query('feed_id');
        $since = $request->query('since');

        $query = Post::query()
            ->whereHas('feed', fn ($feedQuery) => $feedQuery->where('workspace_id', $workspace->id))
            ->orderByDesc('pinned')
            ->orderByDesc('posted_at');

        if (is_numeric($feedId) && (int) $feedId > 0) {
            $query->where('feed_id', (int) $feedId);
        }

        if (is_string($since) && $since !== '') {
            try {
                $query->where('updated_at', '>', Carbon::parse($since));
            } catch (\Throwable) {
                // Ignore invalid since values and return the unfiltered workspace set.
            }
        }

        return ApiResponse::success(PostResource::collection($query->get()));
    }

    public function index(Request $request, Workspace $workspace, Feed $feed): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);

        $status = $request->query('status');

        $query = $feed->posts()
            ->orderByDesc('pinned')
            ->orderByDesc('posted_at');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return ApiResponse::success(PostResource::collection($query->get()));
    }

    public function update(UpdatePostRequest $request, Workspace $workspace, Feed $feed, Post $post): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);
        $this->ensurePostInFeed($feed, $post);

        $post = $this->postService->updatePost($post, $feed, PostUpdateData::fromArray($request->validated()), $request->user());

        return ApiResponse::success(new PostResource($post), 'Post updated.');
    }

    public function bulkUpdate(BulkUpdatePostsRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $validated = $request->validated();
        $postIds = array_values(array_unique($validated['post_ids']));

        $posts = Post::query()
            ->whereIn('id', $postIds)
            ->whereHas('feed', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->get();

        if ($posts->count() !== count($postIds)) {
            abort(404, 'One or more posts were not found.');
        }

        Post::query()
            ->whereIn('id', $postIds)
            ->update(['status' => $validated['status']]);

        $updatedPosts = Post::query()
            ->whereIn('id', $postIds)
            ->orderByDesc('pinned')
            ->orderByDesc('posted_at')
            ->get();

        return ApiResponse::success(PostResource::collection($updatedPosts), 'Posts updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Feed $feed, Post $post): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureFeedInWorkspace($workspace, $feed);
        $this->ensurePostInFeed($feed, $post);

        $this->postService->deletePost($post, $feed, $request->user());

        return ApiResponse::noContent();
    }

    private function authorizeOwner(Request $request, Workspace $workspace): void
    {
        if ($workspace->owner_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }

    private function ensureFeedInWorkspace(Workspace $workspace, Feed $feed): void
    {
        if ($feed->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    private function ensurePostInFeed(Feed $feed, Post $post): void
    {
        if ($post->feed_id !== $feed->id) {
            abort(404);
        }
    }
}
