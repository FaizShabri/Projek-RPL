<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'role',
        'toko_id',
        'invited_by',
        'token_hash',
        'expires_at',
        'used_at',
        'cancelled_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return is_null($this->used_at) && is_null($this->cancelled_at) && $this->expires_at->isFuture();
    }

    public function scopePending($query)
    {
        return $query->whereNull('used_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }
}
