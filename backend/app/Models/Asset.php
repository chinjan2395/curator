<?php

namespace App\Models;

use App\Support\AssetUrl;
use App\Support\GoogleDriveConfig;
use App\Support\GoogleDriveUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    protected $appends = ['url'];
    protected $fillable = [
        'user_id',
        'campaign_id',
        'type',
        'file_name',
        'file_size',
        'mime_type',
        'storage_path',
        'storage_disk',
        'thumbnail_path',
        'ai_tags',
        'width',
        'height',
        'duration',
    ];

    protected $casts = [
        'ai_tags' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->storage_path || ! $this->resolveStorageDisk()) {
            return null;
        }

        return AssetUrl::preview($this);
    }

    public function resolveStorageDisk(): ?string
    {
        foreach ($this->candidateStorageDisks() as $disk) {
            try {
                if (Storage::disk($disk)->exists($this->storage_path)) {
                    return $disk;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function candidateStorageDisks(): array
    {
        $preferred = $this->storage_disk;
        $fallback = GoogleDriveConfig::isConfigured() ? ['googledrive', 'public'] : ['public'];

        if (! is_string($preferred) || $preferred === '') {
            return $fallback;
        }

        $others = array_values(array_filter($fallback, static fn (string $disk) => $disk !== $preferred));

        return array_merge([$preferred], $others);
    }

    public function externalUrl(): ?string
    {
        $disk = $this->resolveStorageDisk();
        if ($disk === null) {
            return null;
        }

        try {
            $url = Storage::disk($disk)->url($this->storage_path);
            if ($url === '') {
                return null;
            }

            if ($disk === 'googledrive' && GoogleDriveUrl::isGoogleDriveUrl($url)) {
                return GoogleDriveUrl::toThumbnailUrl($url) ?? $url;
            }

            return $url;
        } catch (\Throwable) {
            return null;
        }
    }

}
