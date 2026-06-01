<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Campaign;
use App\Models\ContentPackage;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\SocialCredential;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetupStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $workspaceIds = Workspace::where('owner_id', $user->id)->pluck('id');
        $feedIds = DB::table('feeds')->whereIn('workspace_id', $workspaceIds)->pluck('id');

        $hasSyncedPosts = $feedIds->isNotEmpty()
            && Post::query()->whereIn('feed_id', $feedIds)->exists();

        return ApiResponse::success([
            'is_onboarded' => (bool) $user->is_onboarded,
            'has_social_credentials' => SocialCredential::where('user_id', $user->id)->exists(),
            'has_workspaces' => $workspaceIds->isNotEmpty(),
            'has_feeds' => $feedIds->isNotEmpty(),
            'has_synced_posts' => $hasSyncedPosts,
            'has_campaigns' => Campaign::where('user_id', $user->id)->exists(),
            'has_approved_packages' => ContentPackage::where('user_id', $user->id)
                ->where('status', 'approved')
                ->exists(),
            'has_scheduled_posts' => ScheduledPost::where('user_id', $user->id)->exists(),
        ]);
    }
}
