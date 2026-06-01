<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'in_app',
        'email',
        'push',
    ];

    protected $casts = [
        'in_app' => 'boolean',
        'email' => 'boolean',
        'push' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
