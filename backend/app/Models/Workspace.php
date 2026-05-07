<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'owner_id',
        'public_key',
        'publish_settings',
        'last_published_at',
    ];

    protected $casts = [
        'publish_settings' => 'array',
        'last_published_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function feeds()
    {
        return $this->hasMany(Feed::class);
    }
}
