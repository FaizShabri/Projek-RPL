<?php

namespace App\Policies;

use App\Models\Toko;
use App\Models\User;
use App\Enums\UserRole;

class TokoPolicy
{
    /**
     * Boleh melihat daftar toko sendiri atau semua toko (untuk platform admin).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::PLATFORM_OWNER->value,
            UserRole::ADMIN_OWNER->value,
            UserRole::OWNER_TOKO->value,
            UserRole::ADMIN_TOKO->value,
        ]);
    }

    /**
     * Boleh melihat detail toko spesifik.
     */
    public function view(User $user, Toko $toko): bool
    {
        if ($user->hasAnyRole([UserRole::PLATFORM_OWNER->value, UserRole::ADMIN_OWNER->value])) {
            return true;
        }

        if ($user->hasRole(UserRole::OWNER_TOKO->value) && $toko->owner_id === $user->id) {
            return true;
        }

        if ($user->hasRole(UserRole::ADMIN_TOKO->value) && $toko->staff()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Boleh mendaftarkan/onboarding toko baru (hanya owner_toko).
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::OWNER_TOKO->value);
    }

    /**
     * Boleh mengubah data toko (owner_id atau admin_toko yang ditugaskan).
     */
    public function update(User $user, Toko $toko): bool
    {
        if ($user->hasRole(UserRole::OWNER_TOKO->value) && $toko->owner_id === $user->id) {
            return true;
        }

        if ($user->hasRole(UserRole::ADMIN_TOKO->value) && $toko->staff()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Boleh mengelola (tambah/hapus) admin toko / staff.
     * HANYA owner toko yang berhak! (Admin toko tidak boleh menambah staff lain).
     */
    public function manageStaff(User $user, Toko $toko): bool
    {
        return $user->hasRole(UserRole::OWNER_TOKO->value) && $toko->owner_id === $user->id;
    }

    /**
     * Boleh mengubah status approval toko (submitted -> approved/rejected/suspended).
     * HANYA platform_owner atau admin_owner (memiliki permission manage_tokos_platform).
     */
    public function changeStatus(User $user, Toko $toko): bool
    {
        return $user->hasPermissionTo('manage_tokos_platform') ||
            $user->hasAnyRole([UserRole::PLATFORM_OWNER->value, UserRole::ADMIN_OWNER->value]);
    }
}
