<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Toko extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'nama',
        'alamat',
        'latitude',
        'longitude',
        'dokumen_legalitas',
        'status', // 'submitted', 'approved', 'rejected', 'suspended'
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Scope untuk toko yang sudah di-approve dan aktif di marketplace.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Owner dari toko (User dengan role owner_toko).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Relasi ke staff_tokos pivot table.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(StaffToko::class, 'toko_id');
    }

    /**
     * User yang menjadi admin_toko untuk toko ini.
     */
    public function staffUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_tokos', 'toko_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Daftar tarif layanan untuk toko ini.
     */
    public function tarifs(): HasMany
    {
        return $this->hasMany(Tarif::class, 'toko_id');
    }

    /**
     * Daftar order untuk toko ini.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'toko_id');
    }

    /**
     * Daftar review untuk toko ini.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'toko_id');
    }
}
