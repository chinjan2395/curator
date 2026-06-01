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
        'ai_strategy',
    ];

    protected $casts = [
        'target_audience' => 'array',
        'goals' => 'array',
        'platforms' => 'array',
        'ai_strategy' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contentPackages(): HasMany
    {
        return $this->hasMany(ContentPackage::class);
    }
}
