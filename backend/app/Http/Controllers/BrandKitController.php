<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\BrandKit;
use App\Support\BrandKitSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandKitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $kits = BrandKit::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($kits);
    }

    public function show(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        return ApiResponse::success($brandKit);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateKit($request);
        $normalized = $this->normalizePayload($validated);

        $userId = $request->user()->id;
        $hasKits = BrandKit::where('user_id', $userId)->exists();
        $makeDefault = (bool) ($validated['is_default'] ?? false) || ! $hasKits;

        $kit = DB::transaction(function () use ($userId, $validated, $normalized, $makeDefault) {
            if ($makeDefault) {
                BrandKit::where('user_id', $userId)->update(['is_default' => false]);
            }

            return BrandKit::create([
                'user_id' => $userId,
                'name' => $validated['name'],
                'is_default' => $makeDefault,
                'logo_url' => $normalized['logo_url'],
                'colors' => $normalized['colors'],
                'fonts' => $normalized['fonts'],
                'watermark' => $normalized['watermark'],
            ]);
        });

        return ApiResponse::success($kit, 'Brand kit created.', 201);
    }

    public function update(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $validated = $this->validateKit($request, partial: true);
        $normalized = $this->normalizePayload($validated, $brandKit);

        DB::transaction(function () use ($request, $brandKit, $validated, $normalized) {
            if (! empty($validated['is_default'])) {
                BrandKit::where('user_id', $request->user()->id)
                    ->where('id', '!=', $brandKit->id)
                    ->update(['is_default' => false]);
            }

            $brandKit->update(array_filter([
                'name' => $validated['name'] ?? null,
                'is_default' => array_key_exists('is_default', $validated) ? (bool) $validated['is_default'] : null,
                'logo_url' => array_key_exists('logo_url', $validated) ? $normalized['logo_url'] : null,
                'colors' => array_key_exists('colors', $validated) ? $normalized['colors'] : null,
                'fonts' => array_key_exists('fonts', $validated) ? $normalized['fonts'] : null,
                'watermark' => array_key_exists('watermark', $validated) ? $normalized['watermark'] : null,
            ], static fn ($v) => $v !== null));
        });

        return ApiResponse::success($brandKit->fresh(), 'Brand kit updated.');
    }

    public function destroy(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $wasDefault = $brandKit->is_default;
        $userId = $brandKit->user_id;

        DB::transaction(function () use ($brandKit, $wasDefault, $userId) {
            $brandKit->delete();

            if ($wasDefault) {
                $next = BrandKit::where('user_id', $userId)->orderBy('name')->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }
        });

        return ApiResponse::success(null, 'Brand kit deleted.');
    }

    /** @return array<string, mixed> */
    private function validateKit(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'colors' => ['nullable', 'array'],
            'fonts' => ['nullable', 'array'],
            'watermark' => ['nullable', 'array'],
        ];

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{logo_url: ?string, colors: array<string, string>, fonts: array<string, string>, watermark: array<string, mixed>}
     */
    private function normalizePayload(array $validated, ?BrandKit $existing = null): array
    {
        $colors = array_key_exists('colors', $validated)
            ? $validated['colors']
            : ($existing?->colors);
        $fonts = array_key_exists('fonts', $validated)
            ? $validated['fonts']
            : ($existing?->fonts);
        $watermark = array_key_exists('watermark', $validated)
            ? $validated['watermark']
            : ($existing?->watermark);

        $normalized = BrandKitSettings::normalize(
            is_array($colors) ? $colors : null,
            is_array($fonts) ? $fonts : null,
            is_array($watermark) ? $watermark : null,
        );

        $logoUrl = array_key_exists('logo_url', $validated)
            ? BrandKitSettings::sanitizeLogoUrl($validated['logo_url'])
            : $existing?->logo_url;

        return [
            'logo_url' => $logoUrl,
            'colors' => $normalized['colors'],
            'fonts' => $normalized['fonts'],
            'watermark' => $normalized['watermark'],
        ];
    }

    private function authorizeKit(Request $request, BrandKit $brandKit): void
    {
        abort_unless($brandKit->user_id === $request->user()->id, 403);
    }
}
