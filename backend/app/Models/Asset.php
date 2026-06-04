<?php

namespace App\Models;

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
        if (! $this->storage_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->storage_path);
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }
}
