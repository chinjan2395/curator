<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPackage extends Model
{
    protected $fillable = [
        'campaign_id',
        'user_id',
        'platform',
        'content_type',
        'caption',
        'media_urls',
        'hashtags',
        'platform_specific_data',
        'ai_score',
        'status',
        'version',
        'parent_id',
        'variant_group_id',
        'variant_index',
        'is_winner',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'hashtags' => 'array',
        'platform_specific_data' => 'array',
        'ai_score' => 'float',
        'is_winner' => 'boolean',
        'variant_index' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True when this package belongs to an A/B variant group */
    public function isVariant(): bool
    {
        return $this->variant_group_id !== null;
    }

    /** All packages in the same variant group, ordered by variant_index */
    public function variantSiblings(): Collection
    {
        if (! $this->variant_group_id) {
            return new Collection([$this]);
        }

        return static::query()
            ->where('variant_group_id', $this->variant_group_id)
            ->orderBy('variant_index')
            ->get();
    }

    /** Scope: only original packages (not part of any variant group) */
    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('variant_group_id');
    }

    /** Scope: only variant groups (index > 0 = the generated variants, not the original seed) */
    public function scopeVariants(Builder $query): Builder
    {
        return $query->whereNotNull('variant_group_id');
    }

    /** Human-readable label: "Original", "Variant A", "Variant B", "Variant C" */
    public function variantLabel(): string
    {
        if ($this->variant_index === 0) {
            return 'Original';
        }

        return 'Variant '.chr(64 + $this->variant_index);
    }
}
