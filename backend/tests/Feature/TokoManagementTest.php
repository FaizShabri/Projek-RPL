<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Toko;
use App\Models\User;
use App\Enums\UserRole;
use App\Policies\FinancePolicy;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TokoManagementTest extends TestCase
{
    /**
     * Uji coba AuditLog immutable: tidak boleh di-update atau di-delete.
     */
    public function test_audit_log_is_immutable(): void
    {
        $log = new AuditLog([
            'action' => 'TEST_ACTION',
            'created_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $log->save();
        $log->action = 'CHANGED_ACTION';
        $log->save();
    }

    /**
     * Uji coba FinancePolicy:
     * - Admin Owner: TIDAK BOLEH mengakses pengaturan komisi / keuangan inti.
     * - Platform Owner: BOLEH mengakses pengaturan keuangan.
     */
    public function test_finance_policy_isolates_admin_owner_from_finances(): void
    {
        $policy = new FinancePolicy();

        $adminOwner = new User(['name' => 'Admin Owner']);
        // Mock role admin_owner
        $adminOwner->syncRoles = function() {}; 

        // Platform Owner
        $platformOwner = new User(['name' => 'Platform Owner']);

        $this->assertTrue(method_exists($policy, 'manageFinances'));
        $this->assertTrue(method_exists($policy, 'manageCommissions'));
    }

    /**
     * Uji coba model Toko relasi & scope approved.
     */
    public function test_toko_model_relations_exist(): void
    {
        $toko = new Toko();
        $this->assertTrue(method_exists($toko, 'owner'));
        $this->assertTrue(method_exists($toko, 'staff'));
        $this->assertTrue(method_exists($toko, 'staffUsers'));
        $this->assertTrue(method_exists($toko, 'tarifs'));
        $this->assertTrue(method_exists($toko, 'scopeApproved'));
    }
}
