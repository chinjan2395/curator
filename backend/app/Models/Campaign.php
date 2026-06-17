<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'product_info',
        'target_audience',
        'tone',
        'goals',
        'platforms',
        'status',
        'auto_pilot_enabled',
        'ai_strategy',
        'brand_kit_id',
        'template_id',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'goals' => 'array',
        'platforms' => 'array',
        'ai_strategy' => 'array',
        'auto_pilot_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contentPackages(): HasMany
    {
        return $this->hasMany(ContentPackage::class);
    }

    public function brandKit(): BelongsTo
    {
        return $this->belongsTo(BrandKit::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContentTemplate::class);
    }
}
