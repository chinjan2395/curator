<?php

namespace App\Http\Controllers;

use App\DTOs\FeedData;
use App\Http\Requests\StoreFeedRequest;
use App\Http\Requests\UpdateFeedRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\FeedResource;
use App\Models\Feed;
use App\Models\Workspace;
use App\Services\FeedService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function __construct(private readonly FeedService $feedService) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        return ApiResponse::success(
            FeedResource::collection($this->feedService->listForWorkspace($workspace))
        );
    }

    public function store(StoreFeedRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);

        $feed = $this->feedService->createFeed($workspace, FeedData::fromArray($request->validated()), $request->user());

        ActivityLogger::log($request->user(), 'feed.created', "Created {$feed->type} feed \"{$feed->name}\"", 'feed', $feed->id, $feed->name);

        return ApiResponse::success(new FeedResource($feed), 'Feed created.', 201);
    }

    public function show(Request $request, Workspace $workspace, Feed $feed): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureFeedBelongsToWorkspace($workspace, $feed);

        return ApiResponse::success(new FeedResource($feed));
    }

    public function update(UpdateFeedRequest $request, Workspace $workspace, Feed $feed): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureFeedBelongsToWorkspace($workspace, $feed);

        if ($feed->posts()->where('status', 'approved')->exists()) {
            return ApiResponse::error('This feed has accepted posts and cannot be edited. Remove or unaccept those posts first.');
        }

        $feed = $this->feedService->updateFeed($feed, FeedData::fromArray($request->validated()), $request->user());

        ActivityLogger::log($request->user(), 'feed.updated', "Updated feed \"{$feed->name}\"", 'feed', $feed->id, $feed->name);

        return ApiResponse::success(new FeedResource($feed), 'Feed updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Feed $feed): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureFeedBelongsToWorkspace($workspace, $feed);

        if ($feed->posts()->where('status', 'approved')->exists()) {
            return ApiResponse::error('This feed has accepted posts and cannot be deleted. Remove or unaccept those posts first.');
        }

        ActivityLogger::log($request->user(), 'feed.deleted', "Deleted feed \"{$feed->name}\"", 'feed', $feed->id, $feed->name);

        $this->feedService->deleteFeed($feed);

        return ApiResponse::noContent();
    }

    private function authorizeOwner(Request $request, Workspace $workspace): void
    {
        if ($workspace->owner_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }
    }

    private function ensureFeedBelongsToWorkspace(Workspace $workspace, Feed $feed): void
    {
        if ($feed->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
