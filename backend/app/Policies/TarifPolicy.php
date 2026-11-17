<?php

namespace App\Policies;

use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use App\Enums\UserRole;

class TarifPolicy
{
    /**
     * Boleh melihat daftar tarif toko.
     */
    public function view(User $user, Tarif $tarif): bool
    {
        return $this->canAccessToko($user, $tarif->toko);
    }

    /**
     * Boleh membuat tarif baru di toko spesifik.
     */
    public function create(User $user, Toko $toko): bool
    {
        return $this->canAccessToko($user, $toko);
    }

    /**
     * Boleh mengupdate tarif.
     */
    public function update(User $user, Tarif $tarif): bool
    {
        return $this->canAccessToko($user, $tarif->toko);
    }

    /**
     * Boleh menghapus tarif.
     */
    public function delete(User $user, Tarif $tarif): bool
    {
        return $this->canAccessToko($user, $tarif->toko);
    }

    /**
     * Helper verifikasi akses scoped toko:
     * - Owner Toko jika toko->owner_id === user->id
     * - Admin Toko jika user tercatat di staff_tokos untuk toko tersebut
     */
    protected function canAccessToko(User $user, Toko $toko): bool
    {
        if ($user->hasRole(UserRole::OWNER_TOKO->value) && $toko->owner_id === $user->id) {
            return true;
        }

        if ($user->hasRole(UserRole::ADMIN_TOKO->value) && $toko->staff()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }
}
