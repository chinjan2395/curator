<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\ContentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = ContentTemplate::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return ApiResponse::success($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'content_type' => ['nullable', 'string', 'max:50'],
            'template_data' => ['required', 'array'],
        ]);

        $item = ContentTemplate::create(array_merge($validated, ['user_id' => $request->user()->id]));

        return ApiResponse::success($item, 'Template created.', 201);
    }

    public function update(Request $request, ContentTemplate $contentTemplate): JsonResponse
    {
        abort_unless($contentTemplate->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'content_type' => ['nullable', 'string', 'max:50'],
            'template_data' => ['sometimes', 'array'],
        ]);

        $contentTemplate->update($validated);

        return ApiResponse::success($contentTemplate->fresh(), 'Template updated.');
    }

    public function destroy(Request $request, ContentTemplate $contentTemplate): JsonResponse
    {
        abort_unless($contentTemplate->user_id === $request->user()->id, 403);
        $contentTemplate->delete();

        return ApiResponse::success(null, 'Template deleted.');
    }
}
