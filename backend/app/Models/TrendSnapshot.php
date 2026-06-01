<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrendSnapshot extends Model
{
    protected $fillable = [
        'source',
        'title',
        'summary',
        'payload',
        'captured_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'captured_at' => 'datetime',
    ];
}
