<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'feed_id',
        'user_id',
        'provider',
        'feed_name',
        'status',
        'posts_synced',
        'error_message',
        'duration_ms',
        'triggered_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function feed()
    {
        return $this->belongsTo(Feed::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}
