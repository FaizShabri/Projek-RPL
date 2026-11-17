<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'active_role',
        'user_message',
        'assistant_message',
        'tools_used',
        'token_usage',
        'created_at',
    ];

    protected $casts = [
        'tools_used' => 'array',
        'token_usage' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
