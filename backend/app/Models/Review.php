<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'toko_id',
        'kurir_id',
        'rating',
        'komentar',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Relasi ke order yang direview.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Relasi ke toko yang direview (jika review untuk toko).
     */
    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class, 'toko_id');
    }

    /**
     * Relasi ke kurir yang direview (jika review untuk kurir).
     */
    public function kurir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurir_id');
    }

    /**
     * User customer yang memberikan review melalui relasi order.
     */
    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, Order::class, 'id', 'id', 'order_id', 'customer_id');
    }
}
