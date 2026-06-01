<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMessage extends Model
{
    protected $fillable = [
        'user_id',
        'social_credential_id',
        'platform',
        'message_type',
        'external_id',
        'author_name',
        'body',
        'post_url',
        'received_at',
        'replied_at',
        'raw_data',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'replied_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
