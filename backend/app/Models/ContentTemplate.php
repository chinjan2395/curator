<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'platform',
        'content_type',
        'template_data',
    ];

    protected $casts = [
        'template_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
