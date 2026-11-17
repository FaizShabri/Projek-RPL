<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Review;
use App\Models\Toko;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleDashboardSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * 1. Platform Owner dapat mengakses overview, keuangan, komisi, settlement, dan audit log.
     */
    public function test_platform_owner_can_access_overview_finances_and_audit_logs(): void
    {
        $platformOwner = User::factory()->create(['name' => 'Super Platform Owner']);
        $platformOwner->assignRole(UserRole::PLATFORM_OWNER->value);
        Sanctum::actingAs($platformOwner, ['*']);

        $headers = [
            'X-Active-Role' => UserRole::PLATFORM_OWNER->value,
            'Accept' => 'application/json',
        ];

        // Overview
        $res = $this->withHeaders($headers)->getJson('/api/platform/overview');
        $res->assertStatus(200)->assertJsonStructure(['overview' => ['total_users', 'total_gmv', 'total_commission']]);

        // Commission settings
        $resCommission = $this->withHeaders($headers)->getJson('/api/platform/finances/commission');
        $resCommission->assertStatus(200)->assertJsonStructure(['platform_finance' => ['commission_rate_percentage']]);

        // Settlements
        $resSettlements = $this->withHeaders($headers)->getJson('/api/platform/finances/settlements');
        $resSettlements->assertStatus(200)->assertJsonStructure(['settlements', 'summary']);

        // Audit Logs
        $resAudit = $this->withHeaders($headers)->getJson('/api/platform/audit-logs');
        $resAudit->assertStatus(200)->assertJsonStructure(['data']);
    }

    /**
     * 2. BACKEND ENFORCEMENT: Admin Owner DILARANG KERAS mengakses komisi, keuangan, settlement, dan audit log.
     */
    public function test_admin_owner_cannot_access_finances_commission_or_audit_logs(): void
    {
        $adminOwner = User::factory()->create(['name' => 'Operational Admin']);
        $adminOwner->assignRole(UserRole::ADMIN_OWNER->value);
        Sanctum::actingAs($adminOwner, ['*']);

        $headers = [
            'X-Active-Role' => UserRole::ADMIN_OWNER->value,
            'Accept' => 'application/json',
        ];

        // Ditolak dari Overview keuangan
        $this->withHeaders($headers)->getJson('/api/platform/overview')->assertStatus(403);

        // Ditolak dari pengaturan komisi
        $this->withHeaders($headers)->getJson('/api/platform/finances/commission')->assertStatus(403);
        $this->withHeaders($headers)->putJson('/api/platform/finances/commission', [
            'commission_rate_percentage' => 15,
            'min_settlement_amount' => 100000,
        ])->assertStatus(403);

        // Ditolak dari settlement laporan keuangan
        $this->withHeaders($headers)->getJson('/api/platform/finances/settlements')->assertStatus(403);

        // Ditolak dari audit log platform
        $this->withHeaders($headers)->getJson('/api/platform/audit-logs')->assertStatus(403);
    }

    /**
     * 3. Admin Owner BISA mengakses approval toko, kelola dispute, dan kelola user.
     */
    public function test_admin_owner_can_manage_tokos_disputes_and_users(): void
    {
        $adminOwner = User::factory()->create(['name' => 'Operational Admin']);
        $adminOwner->assignRole(UserRole::ADMIN_OWNER->value);
        Sanctum::actingAs($adminOwner, ['*']);

        $headers = [
            'X-Active-Role' => UserRole::ADMIN_OWNER->value,
            'Accept' => 'application/json',
        ];

        // Toko listing for approval
        $this->withHeaders($headers)->getJson('/api/platform/tokos')->assertStatus(200);

        // User list
        $targetUser = User::factory()->create(['name' => 'User Biasa', 'status' => 'active']);
        $resUsers = $this->withHeaders($headers)->getJson('/api/platform/users');
        $resUsers->assertStatus(200);

        // Toggle user status
        $resToggle = $this->withHeaders($headers)->patchJson("/api/platform/users/{$targetUser->id}/status");
        $resToggle->assertStatus(200);
        $this->assertEquals('suspended', $targetUser->fresh()->status);

        // Disputes
        $resDisputes = $this->withHeaders($headers)->getJson('/api/platform/disputes');
        $resDisputes->assertStatus(200);
    }

    /**
     * 4. Owner Toko dapat melihat laporan settlement tokonya sendiri.
     */
    public function test_owner_toko_can_view_own_settlement_report(): void
    {
        $owner = User::factory()->create(['name' => 'Toko Boss']);
        $owner->assignRole(UserRole::OWNER_TOKO->value);
        Sanctum::actingAs($owner, ['*']);

        $toko = Toko::create([
            'nama' => 'Laundry Sejahtera',
            'owner_id' => $owner->id,
            'alamat' => 'Jl. Merdeka No 1',
            'status' => 'approved',
        ]);

        $headers = [
            'X-Active-Role' => UserRole::OWNER_TOKO->value,
            'Accept' => 'application/json',
        ];

        $res = $this->withHeaders($headers)->getJson("/api/owner/tokos/{$toko->id}/settlement");
        $res->assertStatus(200)->assertJsonStructure([
            'toko',
            'settlement_summary' => [
                'gross_revenue',
                'platform_fee_amount',
                'net_earnings',
            ],
        ]);
    }

    /**
     * 5. Kurir dan Customer dapat melihat review mereka masing-masing.
     */
    public function test_kurir_and_customer_can_view_their_reviews(): void
    {
        // Kurir
        $kurir = User::factory()->create(['name' => 'Kurir Kilat']);
        $kurir->assignRole(UserRole::KURIR->value);
        Sanctum::actingAs($kurir, ['*']);

        $resKurir = $this->withHeaders([
            'X-Active-Role' => UserRole::KURIR->value,
            'Accept' => 'application/json',
        ])->getJson('/api/kurir/reviews');
        $resKurir->assertStatus(200)->assertJsonStructure(['average_rating', 'total_reviews', 'reviews']);

        // Customer
        $customer = User::factory()->create(['name' => 'Customer Laundry']);
        $customer->assignRole(UserRole::CUSTOMER->value);
        Sanctum::actingAs($customer, ['*']);

        $resCust = $this->withHeaders([
            'X-Active-Role' => UserRole::CUSTOMER->value,
            'Accept' => 'application/json',
        ])->getJson('/api/customer/reviews');
        $resCust->assertStatus(200)->assertJsonStructure(['data']);
    }
}
