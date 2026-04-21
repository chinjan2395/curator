<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feed extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'source_url',
        'social_credential_id',
        'youtube_channel_id',
        'youtube_uploads_playlist_id',
        'facebook_page_id',
        'instagram_business_account_id',
        'twitter_username',
        'last_synced_at',
        'public_key',
        'publish_settings',
        'last_published_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_published_at' => 'datetime',
        'publish_settings' => 'array',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function socialCredential(): BelongsTo
    {
        return $this->belongsTo(SocialCredential::class, 'social_credential_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
