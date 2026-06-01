<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\ContentBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentBlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = ContentBlock::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return ApiResponse::success($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $item = ContentBlock::create(array_merge($validated, ['user_id' => $request->user()->id]));

        return ApiResponse::success($item, 'Content block created.', 201);
    }

    public function update(Request $request, ContentBlock $contentBlock): JsonResponse
    {
        abort_unless($contentBlock->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $contentBlock->update($validated);

        return ApiResponse::success($contentBlock->fresh(), 'Content block updated.');
    }

    public function destroy(Request $request, ContentBlock $contentBlock): JsonResponse
    {
        abort_unless($contentBlock->user_id === $request->user()->id, 403);
        $contentBlock->delete();

        return ApiResponse::success(null, 'Content block deleted.');
    }
}
