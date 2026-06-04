<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Asset;
use App\Services\Content\AssetTaggingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
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
        $path = $file->store('assets/'.$request->user()->id, 'public');

        $asset = Asset::create([
            'user_id' => $request->user()->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'type' => $validated['type'],
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'storage_path' => $path,
            'ai_tags' => [],
        ]);

        $asset->update(['ai_tags' => $tagging->suggestTags($asset)]);

        return ApiResponse::success($asset->fresh(), 'Asset uploaded.', 201);
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        abort_if($asset->user_id !== $request->user()->id, 403);
        Storage::disk('public')->delete($asset->storage_path);
        $asset->delete();

        return ApiResponse::success(null, 'Asset deleted.');
    }
}
