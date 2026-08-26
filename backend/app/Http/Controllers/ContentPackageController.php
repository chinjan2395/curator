<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Jobs\GenerateImageJob;
use App\Jobs\GenerateVariantsJob;
use App\Jobs\RefineContentPackageJob;
use App\Models\Asset;
use App\Models\ContentPackage;
use App\Services\AI\AiContentService;
use App\Services\AI\AiImageGenerationService;
use App\Services\AI\Image\ImageKeyResolver;
use App\Services\Content\AssetStorageService;
use App\Services\LearningPromptService;
use App\Support\AiImageProviders;
use App\Support\AiTextProviders;
use App\Support\ContentPackageMediaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
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
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

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

    public function refine(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'instruction' => ['required', 'string', 'max:2000'],
            'provider' => ['nullable', 'string', Rule::in(AiTextProviders::selectableIds())],
            'model' => ['nullable', 'string', Rule::in(AiTextProviders::allModelIds())],
        ]);

        RefineContentPackageJob::dispatch(
            $contentPackage->id,
            (int) $request->user()->id,
            $validated['instruction'],
            $validated['provider'] ?? null,
            $validated['model'] ?? null,
        );

        return ApiResponse::success(
            ['content_package_id' => $contentPackage->id, 'queued' => true],
            'Refine started.',
            202,
        );
    }

    public function updateStatus(Request $request, ContentPackage $contentPackage, LearningPromptService $learning): JsonResponse
    {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

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
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'caption' => ['required', 'string'],
        ]);

        $contentPackage->update(['caption' => $validated['caption']]);

        return ApiResponse::success($contentPackage->fresh(), 'Caption updated.');
    }

    /**
     * Duplicate a draft as a new version so it can be iterated on
     * without losing the original.
     * POST /api/content-packages/{contentPackage}/duplicate
     */
    public function duplicate(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        $rootId = $contentPackage->parent_id ?? $contentPackage->id;

        $maxVersion = ContentPackage::query()
            ->where('user_id', $contentPackage->user_id)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_id', $rootId);
            })
            ->max('version');

        $duplicate = ContentPackage::create([
            'campaign_id' => $contentPackage->campaign_id,
            'user_id' => $contentPackage->user_id,
            'platform' => $contentPackage->platform,
            'content_type' => $contentPackage->content_type,
            'caption' => $contentPackage->caption,
            'media_urls' => $contentPackage->media_urls,
            'hashtags' => $contentPackage->hashtags,
            'platform_specific_data' => $contentPackage->platform_specific_data,
            'status' => 'draft',
            'version' => (int) ($maxVersion ?? $contentPackage->version) + 1,
            'parent_id' => $rootId,
        ]);

        return ApiResponse::success($duplicate, 'Draft duplicated.');
    }

    /**
     * Delete a draft. Scheduled or already-published packages must be
     * unscheduled first so a live post is never orphaned.
     * DELETE /api/content-packages/{contentPackage}
     */
    public function destroy(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        if (in_array($contentPackage->status, ['scheduled', 'published'], true)) {
            return ApiResponse::error('Cancel the scheduled post before deleting this draft.', null, 422);
        }

        $contentPackage->delete();

        return ApiResponse::success(null, 'Draft deleted.');
    }

    public function versions(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

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

    /**
     * Generate A/B variants for a content package.
     * Creates 3 sibling packages with different tone styles.
     * POST /api/content-packages/{contentPackage}/variants
     */
    public function generateVariants(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        if ($contentPackage->variant_group_id !== null) {
            return ApiResponse::error('This package already has variants. Generate variants from the original package.', null, 422);
        }

        $validated = $request->validate([
            'count' => ['sometimes', 'integer', 'min:1', 'max:3'],
            'provider' => ['nullable', 'string', Rule::in(AiTextProviders::selectableIds())],
            'model' => ['nullable', 'string', Rule::in(AiTextProviders::allModelIds())],
        ]);

        GenerateVariantsJob::dispatch(
            $contentPackage->id,
            (int) $request->user()->id,
            $validated['count'] ?? 3,
            $validated['provider'] ?? null,
            $validated['model'] ?? null,
        );

        return ApiResponse::success(
            ['content_package_id' => $contentPackage->id, 'queued' => true],
            'Variant generation started.',
            202,
        );
    }

    /**
     * Mark a package as the winner of its A/B variant group.
     * Approves the winner and rejects all siblings.
     * POST /api/content-packages/{contentPackage}/winner
     */
    public function markWinner(Request $request, ContentPackage $contentPackage, AiContentService $ai): JsonResponse
    {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        $winner = $ai->markVariantWinner($contentPackage);

        return ApiResponse::success($winner, 'Variant marked as winner.');
    }

    /**
     * Return all packages in the same variant group.
     * GET /api/content-packages/{contentPackage}/variants
     */
    public function variantGroup(Request $request, ContentPackage $contentPackage): JsonResponse
    {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        $siblings = $contentPackage->variantSiblings();

        return ApiResponse::success($siblings);
    }

    /**
     * Compose the image prompt for a content package without generating anything,
     * so the frontend can preview and optionally edit it first.
     * POST /api/content-packages/{contentPackage}/image-prompt-preview
     */
    public function previewImagePrompt(
        Request $request,
        ContentPackage $contentPackage,
        AiImageGenerationService $imageGeneration,
    ): JsonResponse {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'instruction' => ['nullable', 'string', 'max:2000'],
            'reference_asset_id' => ['nullable', 'integer'],
        ]);

        $prompt = $imageGeneration->previewPrompt(
            $contentPackage,
            $validated['instruction'] ?? null,
            $validated['reference_asset_id'] ?? null,
        );

        return ApiResponse::success(['prompt' => $prompt]);
    }

    /**
     * Generate an AI image for a content package, store as asset, attach to media_urls.
     * POST /api/content-packages/{contentPackage}/generate-image
     *
     * A reference image may be supplied either as an upload (`reference`) or as an
     * asset already in the user's library (`reference_asset_id`). Uploads are stored
     * as assets first, so both routes reach the job as a plain asset id.
     */
    public function generateImage(
        Request $request,
        ContentPackage $contentPackage,
        AssetStorageService $assetStorage,
        ImageKeyResolver $keys,
        AiImageGenerationService $imageGeneration,
    ): JsonResponse {
        abort_if(! $request->user()->isSuperAdmin() && $contentPackage->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'instruction' => ['nullable', 'string', 'max:2000'],
            'provider' => ['nullable', 'string', Rule::in(AiImageProviders::selectableIds())],
            'reference_asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'reference' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
            'prompt' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'model' => ['nullable', 'string', Rule::in(AiImageProviders::allModelIds())],
        ]);

        $existingCount = count($contentPackage->media_urls ?? []);
        if ($existingCount >= 4) {
            return ApiResponse::error('This package already has the maximum of 4 media items.', null, 422);
        }

        $upload = $request->file('reference');
        if ($upload && ! empty($validated['reference_asset_id'])) {
            return ApiResponse::error('Supply either a reference upload or a reference asset, not both.', null, 422);
        }

        $user = $request->user();

        // Fail fast with a clear message rather than letting the job die on a 401
        // from the provider.
        $providerId = $imageGeneration->resolveProviderId($validated['provider'] ?? null, $user);
        if (! $keys->isAvailable($providerId, $user)) {
            return ApiResponse::error(
                AiImageProviders::label($providerId).' has no API key. Add your own key in AI Settings, or pick another provider.',
                null,
                422,
            );
        }

        $referenceAssetId = $validated['reference_asset_id'] ?? null;

        if ($referenceAssetId !== null) {
            $reference = Asset::query()->find($referenceAssetId);
            abort_if(! $reference || $reference->user_id !== $user->id, 403);
        }

        if ($upload) {
            $referenceAssetId = $this->storeReferenceUpload($upload, $contentPackage, $assetStorage);
        }

        GenerateImageJob::dispatch(
            $contentPackage->id,
            (int) $user->id,
            $validated['instruction'] ?? null,
            $referenceAssetId,
            $validated['provider'] ?? null,
            $validated['prompt'] ?? null,
            $validated['model'] ?? null,
        );

        return ApiResponse::success(
            [
                'content_package_id' => $contentPackage->id,
                'queued' => true,
                'provider' => $providerId,
                'reference_asset_id' => $referenceAssetId,
            ],
            'Image generation started.',
            202,
        );
    }

    /**
     * Keep an uploaded reference in the user's library so it can be reused and
     * so the job only ever deals with asset ids.
     */
    private function storeReferenceUpload(
        UploadedFile $upload,
        ContentPackage $contentPackage,
        AssetStorageService $assetStorage,
    ): int {
        $stored = $assetStorage->storeUploadedFile($upload, (int) $contentPackage->user_id);

        $asset = Asset::create([
            'user_id' => $contentPackage->user_id,
            'campaign_id' => $contentPackage->campaign_id,
            'type' => 'image',
            'file_name' => $upload->getClientOriginalName() ?: 'reference.png',
            'file_size' => $upload->getSize(),
            'mime_type' => $upload->getMimeType(),
            'storage_path' => $stored['path'],
            'storage_disk' => $stored['disk'],
            'ai_tags' => ['reference'],
        ]);

        return (int) $asset->id;
    }
}
