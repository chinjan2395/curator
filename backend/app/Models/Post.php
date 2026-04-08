<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'feed_id',
        'title',
        'content',
        'thumbnail_url',
        'video_url',
        'posted_at',
        'external_id',
        'status',
        'pinned',
        'published_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'pinned' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function feed()
    {
        return $this->belongsTo(Feed::class);
    }
}
