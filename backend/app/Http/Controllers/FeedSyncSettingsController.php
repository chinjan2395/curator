<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatchFeedSyncSettingsRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\FeedResource;
use App\Models\Feed;
use App\Models\Workspace;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;

class FeedSyncSettingsController extends Controller
{
    public function patch(PatchFeedSyncSettingsRequest $request, Workspace $workspace, Feed $feed): JsonResponse
    {
        $this->authorizeOwner($request, $workspace);
        $this->ensureFeedBelongsToWorkspace($workspace, $feed);

        $autoPublish = $request->boolean('auto_publish_new_posts');

        $feed->update(['auto_publish_new_posts' => $autoPublish]);

        $feedName = $feed->name ?? $feed->type;
        ActivityLogger::log(
            $request->user(),
            'feed.settings_updated',
            "Updated sync settings for feed \"{$feedName}\" (auto-publish: " . ($autoPublish ? 'on' : 'off') . ')',
            'feed', $feed->id, $feedName,
        );

        return ApiResponse::success(new FeedResource($feed->fresh()), 'Sync settings updated.');
    }

    private function authorizeOwner(PatchFeedSyncSettingsRequest $request, Workspace $workspace): void
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
