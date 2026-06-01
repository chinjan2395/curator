<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        return ApiResponse::success($query->orderByDesc('created_at')->paginate(24));
    }

    public function store(Request $request): JsonResponse
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
            'ai_tags' => [Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))],
        ]);

        return ApiResponse::success([
            ...$asset->toArray(),
            'url' => Storage::disk('public')->url($path),
        ], 'Asset uploaded.', 201);
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        abort_if($asset->user_id !== $request->user()->id, 403);
        Storage::disk('public')->delete($asset->storage_path);
        $asset->delete();

        return ApiResponse::success(null, 'Asset deleted.');
    }
}
