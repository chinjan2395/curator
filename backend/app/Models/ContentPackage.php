<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'media_urls' => 'array',
        'hashtags' => 'array',
        'platform_specific_data' => 'array',
        'ai_score' => 'float',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
