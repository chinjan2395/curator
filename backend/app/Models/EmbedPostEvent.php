<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbedPostEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_POST_CLICK = 'post_click';

    public const TYPE_SHARE_CLICK = 'share_click';

    protected $fillable = [
        'workspace_id',
        'post_id',
        'event_type',
        'target_url',
        'page_url',
        'referrer',
        'user_agent',
        'ip_hash',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
