<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandKit extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'logo_url',
        'colors',
        'fonts',
        'watermark',
    ];

    protected $casts = [
        'colors' => 'array',
        'fonts' => 'array',
        'watermark' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
