<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPost extends Model
{
    protected $fillable = [
        'user_id',
        'content_package_id',
        'social_credential_id',
        'scheduled_at',
        'published_at',
        'platform_post_id',
        'platform_post_url',
        'status',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialCredential(): BelongsTo
    {
        return $this->belongsTo(SocialCredential::class);
    }

    public function contentPackage(): BelongsTo
    {
        return $this->belongsTo(ContentPackage::class);
    }
}
