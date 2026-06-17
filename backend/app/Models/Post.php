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
        'post_url',
        'posted_at',
        'external_id',
        'content_type',
        'status',
        'pinned',
        'published_at',
        'likes',
        'comments',
        'shares',
        'views',
        'saves',
        'reach',
        'hashtags',
        'raw_data',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'pinned' => 'boolean',
        'published_at' => 'datetime',
        'hashtags' => 'array',
        'raw_data' => 'array',
    ];

    public function feed()
    {
        return $this->belongsTo(Feed::class);
    }

    public function duplicateGroups()
    {
        return $this->belongsToMany(PostDuplicateGroup::class, 'post_duplicate_group_items', 'post_id', 'group_id');
    }
}
