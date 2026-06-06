<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Asset;
use App\Models\BrandKit;
use App\Support\BrandKitSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BrandKitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $kits = BrandKit::query()
            ->with('logoAsset')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($kits);
    }

    public function show(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);
        $brandKit->load('logoAsset');

        return ApiResponse::success($brandKit);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $validated = $this->validateKit($request);
        $normalized = $this->normalizePayload($validated, userId: $userId);
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
                'logo_asset_id' => $normalized['logo_asset_id'],
                'colors' => $normalized['colors'],
                'fonts' => $normalized['fonts'],
                'watermark' => $normalized['watermark'],
            ]);
        });

        $kit->load('logoAsset');

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
                'logo_asset_id' => array_key_exists('logo_asset_id', $validated) ? $normalized['logo_asset_id'] : null,
                'colors' => array_key_exists('colors', $validated) ? $normalized['colors'] : null,
                'fonts' => array_key_exists('fonts', $validated) ? $normalized['fonts'] : null,
                'watermark' => array_key_exists('watermark', $validated) ? $normalized['watermark'] : null,
            ], static fn ($v) => $v !== null));
        });

        return ApiResponse::success($brandKit->fresh()->load('logoAsset'), 'Brand kit updated.');
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
        $userId = $request->user()->id;

        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'logo_asset_id' => [
                'nullable',
                'integer',
                Rule::exists('assets', 'id')->where(static fn ($query) => $query->where('user_id', $userId)),
            ],
            'colors' => ['nullable', 'array'],
            'fonts' => ['nullable', 'array'],
            'watermark' => ['nullable', 'array'],
        ];

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{logo_url: ?string, logo_asset_id: ?int, colors: array<string, string>, fonts: array<string, string>, watermark: array<string, mixed>}
     */
    private function normalizePayload(array $validated, ?BrandKit $existing = null, ?int $userId = null): array
    {
        $userId = $userId ?? $existing?->user_id;
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

        $logoAssetId = array_key_exists('logo_asset_id', $validated)
            ? ($validated['logo_asset_id'] !== null ? (int) $validated['logo_asset_id'] : null)
            : $existing?->logo_asset_id;

        if ($logoAssetId) {
            abort_unless(
                Asset::query()->where('user_id', $userId)->whereKey($logoAssetId)->exists(),
                422,
                'Logo asset not found.',
            );
            $logoUrl = null;
        } else {
            $logoUrl = array_key_exists('logo_url', $validated)
                ? BrandKitSettings::sanitizeLogoUrl($validated['logo_url'])
                : $existing?->getRawOriginal('logo_url');
        }

        return [
            'logo_url' => $logoUrl,
            'logo_asset_id' => $logoAssetId,
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
