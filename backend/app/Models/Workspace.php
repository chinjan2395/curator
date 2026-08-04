<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workspace extends Model
{
    protected $fillable = [
        'name',
        'owner_id',
        'public_key',
        'publish_settings',
        'last_published_at',
        'brand_kit_id',
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

    public function brandKit(): BelongsTo
    {
        return $this->belongsTo(BrandKit::class);
    }

    /**
     * A super admin can access every workspace so they can perform actions on
     * behalf of a user who can't (or doesn't know how to) do it themselves.
     */
    public function isAccessibleBy(User $user): bool
    {
        return $user->isSuperAdmin() || (int) $this->owner_id === (int) $user->id;
    }
}
