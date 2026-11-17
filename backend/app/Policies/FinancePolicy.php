<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRole;

class FinancePolicy
{
    /**
     * Memeriksa apakah user berhak mengelola keuangan inti atau komisi platform.
     * 
     * PERBEDAAN KRUSIAL:
     * - Platform Owner: Memiliki permission 'manage_finances' (seluruh akses keuangan).
     * - Admin Owner: HANYA memiliki 'manage_settings' & 'manage_tokos_platform'.
     *   TIDAK BISA mengakses komisi / keuangan inti platform!
     */
    public function manageFinances(User $user): bool
    {
        // Pengecekan berbasis permission Spatie
        if ($user->hasPermissionTo('manage_finances')) {
            return true;
        }

        // Hard guarantee: admin_owner secara eksplisit dilarang menyentuh keuangan
        if ($user->hasRole(UserRole::ADMIN_OWNER->value)) {
            return false;
        }

        return $user->hasRole(UserRole::PLATFORM_OWNER->value);
    }

    /**
     * Mengatur persentase potongan komisi platform dari transaksi laundry.
     */
    public function manageCommissions(User $user): bool
    {
        return $this->manageFinances($user);
    }

    /**
     * Menyetujui atau mencairkan settlement dana ke mitra toko.
     */
    public function manageSettlements(User $user): bool
    {
        return $this->manageFinances($user);
    }
}
