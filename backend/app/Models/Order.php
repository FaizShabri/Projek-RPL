<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'toko_id',
        'tarif_id',
        'kurir_id',
        'status',
        'alamat_jemput',
        'catatan',
        'berat',
        'total_biaya',
        'metode_bayar',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'berat' => 'float',
        'total_biaya' => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class, 'toko_id');
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class, 'tarif_id');
    }

    public function kurir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kurir_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->orderBy('id', 'desc');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'order_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'order_id');
    }

    /**
     * Helper untuk mengecek apakah order dapat dibatalkan oleh customer (Cancel Window).
     */
    public function canCancelByCustomer(): bool
    {
        return $this->status instanceof OrderStatus
            ? $this->status->canCancelByCustomer()
            : in_array($this->status, [OrderStatus::MENUNGGU_KONFIRMASI->value, OrderStatus::MENUNGGU_KURIR->value]);
    }
}
