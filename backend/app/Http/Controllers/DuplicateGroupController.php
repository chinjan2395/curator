<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Http\Resources\DuplicateGroupResource;
use App\Jobs\DuplicateScanJob;
use App\Models\Post;
use App\Models\PostDuplicateGroup;
use App\Models\Workspace;
use App\Services\DuplicateDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuplicateGroupController extends Controller
{
    public function __construct(private readonly DuplicateDetectionService $detector) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $groups = PostDuplicateGroup::where('workspace_id', $workspace->id)
            ->where('status', 'open')
            ->with(['posts'])
            ->get();

        return ApiResponse::success(DuplicateGroupResource::collection($groups));
    }

    public function scan(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        DuplicateScanJob::dispatch($workspace->id, (int) $request->user()->id, 'scan');

        return ApiResponse::success(
            ['workspace_id' => $workspace->id, 'queued' => true],
            'Duplicate scan started.',
            202,
        );
    }

    public function keep(Request $request, Workspace $workspace, PostDuplicateGroup $group, Post $post): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureGroupInWorkspace($workspace, $group);

        if (!$group->posts()->where('post_id', $post->id)->exists()) {
            abort(404, 'Post not found in this group.');
        }

        $otherIds = $group->posts()->where('post_id', '!=', $post->id)->pluck('posts.id');
        Post::whereIn('id', $otherIds)->update(['status' => 'rejected']);

        $group->update(['status' => 'resolved']);

        return ApiResponse::success(null, 'Group resolved.');
    }

    public function dismiss(Request $request, Workspace $workspace, PostDuplicateGroup $group): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureGroupInWorkspace($workspace, $group);

        $group->update(['status' => 'dismissed']);

        return ApiResponse::success(null, 'Group dismissed.');
    }

    private function authorizeOwner(Request $request, Workspace $workspace): void
    {
        if ($workspace->owner_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }

    private function ensureGroupInWorkspace(Workspace $workspace, PostDuplicateGroup $group): void
    {
        if ($group->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
