<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use App\Models\Asset;
use App\Models\BrandKit;
use App\Support\BrandKitExpandedSettings;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BrandKitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $kits = BrandKit::query()
            ->with(['logoAsset', 'parent'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($kits);
    }

    public function show(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);
        $brandKit->load(['logoAsset', 'parent']);

        return ApiResponse::success($brandKit);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $validated = $this->validateKit($request);
        $parentId = $this->resolveParentId($request, $validated);
        $logo = $this->resolveLogo($validated, null, $userId);

        $hasKits = BrandKit::where('user_id', $userId)->exists();
        $makeDefault = (bool) ($validated['is_default'] ?? false) || ! $hasKits;

        // A root/Master kit created via the flat legacy creation form seeds its
        // identity fields into `overrides` so it behaves like today's flat kit.
        // A child kit (created with `parent_id`) starts sparse: it inherits
        // everything from its Master until the user explicitly overrides a
        // field in the editor.
        $overrides = [];
        if ($parentId === null) {
            $expanded = BrandKitExpandedSettings::validateAndNormalize([
                'colors' => is_array($validated['colors'] ?? null) ? $validated['colors'] : [],
                'fonts' => is_array($validated['fonts'] ?? null) ? $validated['fonts'] : [],
                'watermark' => is_array($validated['watermark'] ?? null) ? $validated['watermark'] : [],
            ]);
            // Seed the embed palette from the identity palette so a new Master
            // opens with Brand identity and Embed appearance already in step.
            // Both groups are stored, so every later edit is a plain override.
            $overrides = [
                'colors' => $expanded['colors'],
                'fonts' => $expanded['fonts'],
                'watermark' => $expanded['watermark'],
                'feed_colors' => BrandKitExpandedSettings::validateAndNormalize([
                    'feed_colors' => BrandKitExpandedSettings::feedColorsFromIdentity($expanded['colors']),
                ])['feed_colors'],
            ];
        }

        $kit = DB::transaction(function () use ($userId, $validated, $makeDefault, $logo, $parentId, $overrides) {
            if ($makeDefault) {
                BrandKit::where('user_id', $userId)->update(['is_default' => false]);
            }

            return BrandKit::create([
                'user_id' => $userId,
                'name' => $validated['name'],
                'is_default' => $makeDefault,
                'logo_url' => $logo['logo_url'],
                'logo_asset_id' => $logo['logo_asset_id'],
                'parent_id' => $parentId,
                'overrides' => $overrides,
            ]);
        });

        $kit->load(['logoAsset', 'parent']);

        return ApiResponse::success($kit, 'Brand kit created.', 201);
    }

    public function update(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $validated = $this->validateKit($request, partial: true);
        $logo = $this->resolveLogo($validated, $brandKit, $brandKit->user_id);

        DB::transaction(function () use ($request, $brandKit, $validated, $logo) {
            if (! empty($validated['is_default'])) {
                BrandKit::where('user_id', $request->user()->id)
                    ->where('id', '!=', $brandKit->id)
                    ->update(['is_default' => false]);
            }

            $updates = [];

            if (array_key_exists('name', $validated)) {
                $updates['name'] = $validated['name'];
            }
            if (array_key_exists('is_default', $validated)) {
                $updates['is_default'] = (bool) $validated['is_default'];
            }
            if (array_key_exists('logo_url', $validated) || array_key_exists('logo_asset_id', $validated)) {
                $updates['logo_url'] = $logo['logo_url'];
                $updates['logo_asset_id'] = $logo['logo_asset_id'];
            }

            // Backward-compat with the current frontend, which still saves the
            // full colors/fonts/watermark objects on every edit. This endpoint
            // stays identity-scoped otherwise: `parent_id` and the rest of
            // `overrides` are only mutated via the dedicated overrides/reset
            // endpoints below.
            $legacyPatch = [];
            foreach (['colors', 'fonts', 'watermark'] as $group) {
                if (array_key_exists($group, $validated) && is_array($validated[$group])) {
                    $legacyPatch[$group] = $validated[$group];
                }
            }
            if ($legacyPatch !== []) {
                $updates['overrides'] = BrandKitExpandedSettings::deepMerge($brandKit->overrides ?? [], $legacyPatch);
            }

            $brandKit->update($updates);
        });

        return ApiResponse::success($brandKit->fresh()->load(['logoAsset', 'parent']), 'Brand kit updated.');
    }

    public function destroy(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        if ($brandKit->children()->exists()) {
            return ApiResponse::error('Cannot delete a brand kit that has child kits — reassign or delete them first.');
        }

        $wasDefault = $brandKit->is_default;
        $userId = $brandKit->user_id;

        try {
            DB::transaction(function () use ($brandKit, $wasDefault, $userId) {
                $brandKit->delete();

                if ($wasDefault) {
                    $next = BrandKit::where('user_id', $userId)->orderBy('name')->first();
                    if ($next) {
                        $next->update(['is_default' => true]);
                    }
                }
            });
        } catch (QueryException) {
            // Defense-in-depth against the `restrictOnDelete()` FK for a
            // concurrently-created child kit slipping past the pre-check above.
            return ApiResponse::error('Cannot delete a brand kit that has child kits — reassign or delete them first.');
        }

        return ApiResponse::success(null, 'Brand kit deleted.');
    }

    public function updateOverrides(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $patch = $request->all();
        if (! is_array($patch) || $patch === []) {
            return ApiResponse::error('A non-empty overrides patch is required.');
        }

        $brandKit->overrides = BrandKitExpandedSettings::deepMerge($brandKit->overrides ?? [], $patch);
        $brandKit->save();

        return ApiResponse::success($this->overridesPayload($brandKit), 'Brand kit overrides updated.');
    }

    public function resetOverride(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $path = trim((string) $request->input('path', ''));
        if ($path === '') {
            return ApiResponse::error('A "path" is required.');
        }

        $brandKit->overrides = $this->unsetDotPath($brandKit->overrides ?? [], $path);
        $brandKit->save();

        return ApiResponse::success($this->overridesPayload($brandKit), 'Brand kit override reset.');
    }

    public function duplicate(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $clone = BrandKit::create([
            'user_id' => $brandKit->user_id,
            'name' => $brandKit->name.' (copy)',
            'is_default' => false,
            'logo_url' => $brandKit->getRawOriginal('logo_url'),
            'logo_asset_id' => $brandKit->logo_asset_id,
            'parent_id' => $brandKit->parent_id,
            'overrides' => $brandKit->overrides ?? [],
        ]);

        $clone->load(['logoAsset', 'parent']);

        return ApiResponse::success($clone, 'Brand kit duplicated.', 201);
    }

    public function createChild(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        if ($brandKit->parent_id !== null) {
            return ApiResponse::error('Cannot create a child of a child brand kit.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $child = BrandKit::create([
            'user_id' => $brandKit->user_id,
            'name' => $validated['name'],
            'is_default' => false,
            'logo_url' => null,
            'logo_asset_id' => null,
            'parent_id' => $brandKit->id,
            'overrides' => [],
        ]);

        $child->load(['logoAsset', 'parent']);

        return ApiResponse::success($child, 'Brand kit child created.', 201);
    }

    public function setAsMaster(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        $brandKit->update(['parent_id' => null]);

        return ApiResponse::success($brandKit->fresh()->load(['logoAsset', 'parent']), 'Brand kit set as master.');
    }

    public function resolved(Request $request, BrandKit $brandKit): JsonResponse
    {
        $this->authorizeKit($request, $brandKit);

        return ApiResponse::success([
            'resolved' => $brandKit->resolve(),
            'overridden_paths' => $brandKit->overriddenPaths(),
        ]);
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
            'parent_id' => ['sometimes', 'nullable', 'integer'],
        ];

        return $request->validate($rules);
    }

    /**
     * Validates and resolves an optional `parent_id` from a create payload.
     * Enforces the two-level (Master -> direct children) product decision:
     * the target must exist, belong to this user (or be super-admin owned),
     * and must not itself have a parent.
     */
    private function resolveParentId(Request $request, array $validated): ?int
    {
        if (empty($validated['parent_id'])) {
            return null;
        }

        $parent = BrandKit::find($validated['parent_id']);
        abort_if(! $parent, 422, 'Parent brand kit not found.');
        abort_unless(
            $request->user()->isSuperAdmin() || $parent->user_id === $request->user()->id,
            422,
            'Parent brand kit not found.',
        );
        abort_if($parent->parent_id !== null, 422, 'Cannot nest a brand kit more than two levels deep.');

        return $parent->id;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{logo_url: ?string, logo_asset_id: ?int}
     */
    private function resolveLogo(array $validated, ?BrandKit $existing, ?int $userId): array
    {
        $logoAssetId = array_key_exists('logo_asset_id', $validated)
            ? ($validated['logo_asset_id'] !== null ? (int) $validated['logo_asset_id'] : null)
            : $existing?->logo_asset_id;

        if ($logoAssetId) {
            abort_unless(
                Asset::query()->where('user_id', $userId)->whereKey($logoAssetId)->exists(),
                422,
                'Logo asset not found.',
            );

            return ['logo_url' => null, 'logo_asset_id' => $logoAssetId];
        }

        $logoUrl = array_key_exists('logo_url', $validated)
            ? BrandKitExpandedSettings::sanitizeLogoUrl($validated['logo_url'])
            : $existing?->getRawOriginal('logo_url');

        return ['logo_url' => $logoUrl, 'logo_asset_id' => null];
    }

    /** @return array<string, mixed> */
    private function overridesPayload(BrandKit $brandKit): array
    {
        return [
            'resolved' => $brandKit->resolve(),
            'overrides' => $brandKit->overrides ?? [],
            'overridden_paths' => $brandKit->overriddenPaths(),
        ];
    }

    /**
     * Removes a dot-notation path from a sparse overrides tree so resolution
     * falls through to the parent (or defaults) for that key, rather than
     * overwriting it with a default value.
     *
     * @param  array<string, mixed>  $tree
     * @return array<string, mixed>
     */
    private function unsetDotPath(array $tree, string $path): array
    {
        return self::unsetDotPathSegments($tree, explode('.', $path));
    }

    /**
     * Recursively unsets a dot-path, pruning now-empty parent arrays on the
     * way back up so a reset doesn't leave behind `['feed_colors' => ['post_border' => []]]`
     * dead branches that would otherwise still show up as "overridden".
     *
     * @param  array<string, mixed>  $tree
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private static function unsetDotPathSegments(array $tree, array $segments): array
    {
        $key = array_shift($segments);

        if (! array_key_exists($key, $tree)) {
            return $tree;
        }

        if ($segments === []) {
            unset($tree[$key]);

            return $tree;
        }

        if (! is_array($tree[$key])) {
            return $tree;
        }

        $tree[$key] = self::unsetDotPathSegments($tree[$key], $segments);
        if ($tree[$key] === []) {
            unset($tree[$key]);
        }

        return $tree;
    }

    private function authorizeKit(Request $request, BrandKit $brandKit): void
    {
        abort_unless($request->user()->isSuperAdmin() || $brandKit->user_id === $request->user()->id, 403);
    }
}
