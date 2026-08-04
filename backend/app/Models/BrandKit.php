<?php

namespace App\Models;

use App\Support\BrandKitExpandedSettings;
use App\Support\GoogleDriveUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandKit extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'is_default',
        'logo_url',
        'logo_asset_id',
        'parent_id',
        'overrides',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'overrides' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logoAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'logo_asset_id');
    }

    /**
     * The Master kit this kit inherits from, if any. Product decision is a
     * two-level tree (Master -> direct children), so `parent` never itself
     * has a `parent_id`, but that constraint is enforced at the controller
     * layer, not here.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Direct child kits that inherit from this kit.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Resolves the full, normalized settings tree for this kit by walking
     * root -> leaf up the parent chain and deep-merging each ancestor's
     * sparse `overrides` onto `BrandKitExpandedSettings::defaults()`. A live
     * cascade: always reflects the parent's *current* overrides, never a
     * snapshot.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $chain = [];
        $node = $this;
        while ($node !== null) {
            $chain[] = $node;
            $node = $node->relationLoaded('parent') ? $node->parent : $node->parent()->first();
        }
        $chain = array_reverse($chain); // root -> leaf

        $tree = BrandKitExpandedSettings::defaults();
        foreach ($chain as $kit) {
            $tree = BrandKitExpandedSettings::deepMerge($tree, $kit->overrides ?? []);
        }

        return BrandKitExpandedSettings::validateAndNormalize($tree);
    }

    /**
     * Flattens this kit's own `overrides` (not the resolved tree) into
     * dot-notation paths, e.g. `['colors.accent', 'feed_colors.post_border.color']`,
     * for UI "N overrides" summaries/badges.
     *
     * @return list<string>
     */
    public function overriddenPaths(): array
    {
        return self::flattenPaths($this->overrides ?? []);
    }

    /** @param  array<string, mixed>  $tree */
    private static function flattenPaths(array $tree, string $prefix = ''): array
    {
        $paths = [];

        foreach ($tree as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && ! array_is_list($value) && $value !== []) {
                $paths = array_merge($paths, self::flattenPaths($value, $path));
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    public function resolvedLogoUrl(): ?string
    {
        if ($this->logo_asset_id) {
            $asset = $this->relationLoaded('logoAsset')
                ? $this->logoAsset
                : $this->logoAsset()->first();

            return $asset?->url;
        }

        $stored = $this->attributes['logo_url'] ?? null;
        if (! $stored) {
            if ($this->parent_id) {
                $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();

                return $parent?->resolvedLogoUrl();
            }

            return null;
        }

        if (GoogleDriveUrl::isGoogleDriveUrl($stored)) {
            return GoogleDriveUrl::toThumbnailUrl($stored) ?? $stored;
        }

        return $stored;
    }

    /**
     * Preserves the pre-inheritance flat response shape (`colors`/`fonts`/
     * `watermark` directly on the kit) for the existing frontend, sourced
     * from the live-resolved (inherited + own overrides) tree rather than
     * the raw sparse `overrides` column.
     */
    public function toArray(): array
    {
        $data = parent::toArray();
        $data['logo_url'] = $this->resolvedLogoUrl();

        $resolved = $this->resolve();
        $data['colors'] = $resolved['colors'];
        $data['fonts'] = $resolved['fonts'];
        $data['watermark'] = $resolved['watermark'];

        return $data;
    }
}
