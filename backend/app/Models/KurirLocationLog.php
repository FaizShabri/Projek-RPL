<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurirLocationLog extends Model
{
    /**
     * Tabel ini hanya memiliki created_at, tanpa updated_at.
     */
    public $timestamps = false;

    protected $fillable = [
        'kurir_id',
        'order_id',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude'   => 'float',
        'longitude'  => 'float',
        'created_at' => 'datetime',
    ];

    /**
     * Kurir yang mengirim ping lokasi.
     */
    public function kurir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurir_id');
    }

    /**
     * Order yang sedang di-track.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
