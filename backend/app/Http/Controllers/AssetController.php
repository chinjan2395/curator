<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Asset;
use App\Services\Content\AssetStorageService;
use App\Services\Content\AssetTaggingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetStorageService $assetStorage,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Asset::where('user_id', $request->user()->id);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->query('q')) {
            $query->where('file_name', 'like', '%'.$search.'%');
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 24)));

        return ApiResponse::success($query->orderByDesc('created_at')->paginate($perPage));
    }

    public function store(Request $request, AssetTaggingService $tagging): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'type' => ['required', 'string', 'in:image,video,audio,document,template'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ]);

        $file = $request->file('file');
        $userId = (int) auth()->id();
        $stored = $this->assetStorage->storeUploadedFile($file, $userId);

        $asset = Asset::create([
            'user_id' => $request->user()->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'type' => $validated['type'],
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'storage_path' => $stored['path'],
            'storage_disk' => $stored['disk'],
            'ai_tags' => [],
        ]);

        $asset->update(['ai_tags' => $tagging->suggestTags($asset)]);

        return ApiResponse::success($asset->fresh(), 'Asset uploaded.', 201);
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        abort_if($asset->user_id !== $request->user()->id, 403);

        if ($asset->storage_path) {
            $this->assetStorage->delete($asset);
        }
        $asset->delete();

        return ApiResponse::success(null, 'Asset deleted.');
    }

    public function file(Asset $asset): StreamedResponse
    {
        foreach ($asset->candidateStorageDisks() as $disk) {
            $storage = Storage::disk($disk);

            if (! $storage->exists($asset->storage_path)) {
                continue;
            }

            $stream = $storage->readStream($asset->storage_path);
            $mime = $asset->mime_type ?: $storage->mimeType($asset->storage_path);

            return response()->stream(function () use ($stream) {
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        abort(404);
    }
}
