<?php

namespace App\Models;

use App\Support\GoogleDriveUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandKit extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'is_default',
        'logo_url',
        'logo_asset_id',
        'colors',
        'fonts',
        'watermark',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'colors' => 'array',
        'fonts' => 'array',
        'watermark' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logoAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'logo_asset_id');
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
            return null;
        }

        if (GoogleDriveUrl::isGoogleDriveUrl($stored)) {
            return GoogleDriveUrl::toThumbnailUrl($stored) ?? $stored;
        }

        return $stored;
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        $data['logo_url'] = $this->resolvedLogoUrl();

        return $data;
    }
}
