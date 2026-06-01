<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\ContentPackage;
use App\Services\AI\AiContentService;
use App\Services\LearningPromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContentPackage::query()
            ->where('user_id', $request->user()->id)
            ->with('campaign:id,name');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($platform = $request->query('platform')) {
            $query->where('platform', $platform);
        }

        $packages = $query->orderByDesc('updated_at')->limit(100)->get();

        return ApiResponse::success($packages);
    }

    public function updateMedia(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if($contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'media_urls' => ['required', 'array', 'max:4'],
            'media_urls.*' => ['required', 'url', 'max:2048'],
        ]);

        $contentPackage->update(['media_urls' => $validated['media_urls']]);

        return ApiResponse::success($contentPackage->fresh(), 'Media URLs updated.');
    }

    public function refine(Request $request, ContentPackage $contentPackage, AiContentService $ai, LearningPromptService $learning): JsonResponse
    {
        abort_if($contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'instruction' => ['required', 'string', 'max:2000'],
        ]);

        $refined = $ai->refine($contentPackage, $validated['instruction']);

        $learning->recordAndRefresh($request->user(), 'refined', $contentPackage->platform, [
            'content_package_id' => $contentPackage->id,
            'instruction' => $validated['instruction'],
        ]);

        return ApiResponse::success($refined, 'Content refined.');
    }

    public function updateStatus(Request $request, ContentPackage $contentPackage, LearningPromptService $learning): JsonResponse
    {
        abort_if($contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,in_review,approved,scheduled,published,rejected'],
        ]);

        $contentPackage->update(['status' => $validated['status']]);

        if (in_array($validated['status'], ['approved', 'rejected'], true)) {
            $learning->recordAndRefresh($request->user(), $validated['status'], $contentPackage->platform, [
                'content_package_id' => $contentPackage->id,
            ]);
        }

        return ApiResponse::success($contentPackage->fresh(), 'Status updated.');
    }

    public function versions(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if($contentPackage->user_id !== $request->user()->id, 403);

        $rootId = $contentPackage->parent_id ?? $contentPackage->id;

        $versions = ContentPackage::query()
            ->where('user_id', $request->user()->id)
            ->where(function ($q) use ($rootId, $contentPackage) {
                $q->where('id', $rootId)
                    ->orWhere('parent_id', $rootId)
                    ->orWhere('id', $contentPackage->id)
                    ->orWhere('parent_id', $contentPackage->id);
            })
            ->orderBy('version')
            ->get();

        return ApiResponse::success($versions);
    }
}
