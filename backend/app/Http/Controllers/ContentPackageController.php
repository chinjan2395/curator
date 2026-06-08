<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\ContentPackage;
use App\Services\AI\AiContentService;
use App\Services\LearningPromptService;
use App\Support\ContentPackageMediaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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

    public function updateMedia(
        Request $request,
        ContentPackage $contentPackage,
        ContentPackageMediaResolver $mediaResolver,
    ): JsonResponse {
        abort_if($contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'media_urls' => ['sometimes', 'array', 'max:4'],
            'media_urls.*' => ['required_with:media_urls', 'url', 'max:2048'],
            'asset_ids' => ['sometimes', 'array', 'max:4'],
            'asset_ids.*' => ['integer'],
            'replace' => ['sometimes', 'boolean'],
        ]);

        if (! isset($validated['media_urls']) && ! isset($validated['asset_ids'])) {
            return ApiResponse::error('Provide media_urls and/or asset_ids.', null, 422);
        }

        try {
            $existing = ($validated['replace'] ?? false) ? [] : ($contentPackage->media_urls ?? []);
            $urls = $mediaResolver->merge(
                $existing,
                $validated['media_urls'] ?? null,
                $validated['asset_ids'] ?? null,
                (int) $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }

        $contentPackage->update(['media_urls' => $urls]);

        return ApiResponse::success($contentPackage->fresh(), 'Media updated.');
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

    public function updateCaption(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if($contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'caption' => ['required', 'string'],
        ]);

        $contentPackage->update(['caption' => $validated['caption']]);

        return ApiResponse::success($contentPackage->fresh(), 'Caption updated.');
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
