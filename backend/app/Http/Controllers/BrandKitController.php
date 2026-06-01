<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\BrandKit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandKitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $kits = BrandKit::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return ApiResponse::success($kits);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'colors' => ['nullable', 'array'],
            'fonts' => ['nullable', 'array'],
            'watermark' => ['nullable', 'array'],
        ]);

        $kit = BrandKit::create(array_merge($validated, ['user_id' => $request->user()->id]));

        return ApiResponse::success($kit, 'Brand kit created.', 201);
    }

    public function update(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'colors' => ['nullable', 'array'],
            'fonts' => ['nullable', 'array'],
            'watermark' => ['nullable', 'array'],
        ]);

        $brandKit->update($validated);

        return ApiResponse::success($brandKit->fresh(), 'Brand kit updated.');
    }

    public function destroy(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);
        $brandKit->delete();

        return ApiResponse::success(null, 'Brand kit deleted.');
    }

    private function authorizeKit(Request $request, BrandKit $brandKit): void
    {
        abort_unless($brandKit->user_id === $request->user()->id, 403);
    }
}
