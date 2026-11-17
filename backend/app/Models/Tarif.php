<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarif extends Model
{
    use HasFactory;

    protected $fillable = [
        'toko_id',
        'jenis_layanan',
        'harga',
    ];

    protected $casts = [
        'harga' => 'float',
    ];

    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class, 'toko_id');
    }
}
