<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\KurirLocationLog;
use App\Models\Order;
use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use Database\Seeders\MarketplaceSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KurirTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $kurir;
    protected Toko $toko;
    protected Tarif $tarif;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(MarketplaceSeeder::class);

        $this->customer = User::whereHas('roles', fn($q) => $q->where('name', UserRole::CUSTOMER->value))->first();

        $this->kurir = User::factory()->create(['name' => 'Kurir GPS Test']);
        $this->kurir->assignRole(UserRole::KURIR->value);

        $owner = User::whereHas('roles', fn($q) => $q->where('name', UserRole::OWNER_TOKO->value))->first();
        $this->toko = Toko::where('owner_id', $owner->id)->first();
        $this->tarif = Tarif::where('toko_id', $this->toko->id)->first();
    }

    /**
     * Helper: Buat order dengan status dan kurir tertentu.
     */
    private function createOrder(OrderStatus $status, ?int $kurirId = null): Order
    {
        return Order::create([
            'customer_id'   => $this->customer->id,
            'toko_id'       => $this->toko->id,
            'tarif_id'      => $this->tarif->id,
            'kurir_id'      => $kurirId ?? $this->kurir->id,
            'status'        => $status,
            'alamat_jemput' => 'Jl. Test Tracking No. 1',
            'metode_bayar'  => 'CASH',
        ]);
    }

    // =========================================================
    // KURIR PING TESTS
    // =========================================================

    /**
     * Kurir bisa mengirim ping lokasi saat order dalam status aktif (dijemput).
     */
    public function test_kurir_can_ping_location_during_active_order(): void
    {
        $order = $this->createOrder(OrderStatus::DIJEMPUT);

        $response = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson('/api/kurir/location/ping', [
                'latitude'  => -6.20000000,
                'longitude' => 106.81600000,
                'order_id'  => $order->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'tracked' => true,
        ]);

        $this->assertDatabaseHas('kurir_location_logs', [
            'kurir_id' => $this->kurir->id,
            'order_id' => $order->id,
        ]);
    }

    /**
     * Kurir tidak bisa ping lokasi untuk order yang sudah selesai.
     */
    public function test_kurir_cannot_ping_for_completed_order(): void
    {
        $order = $this->createOrder(OrderStatus::SELESAI);

        $response = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson('/api/kurir/location/ping', [
                'latitude'  => -6.20000000,
                'longitude' => 106.81600000,
                'order_id'  => $order->id,
            ]);

        $response->assertStatus(422);
    }

    // =========================================================
    // CUSTOMER TRACKING TESTS
    // =========================================================

    /**
     * Customer bisa melihat lokasi kurir yang live.
     */
    public function test_customer_can_get_kurir_live_location(): void
    {
        $order = $this->createOrder(OrderStatus::DIJEMPUT);

        // Buat log lokasi terbaru (baru saja)
        KurirLocationLog::create([
            'kurir_id'   => $this->kurir->id,
            'order_id'   => $order->id,
            'latitude'   => -6.20000000,
            'longitude'  => 106.81600000,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->getJson("/api/customer/orders/{$order->id}/tracking");

        $response->assertStatus(200);
        $response->assertJson([
            'tracking_active' => true,
            'status'          => 'live',
            'order_status'    => 'dijemput',
            'kurir_name'      => $this->kurir->name,
        ]);
        $response->assertJsonStructure([
            'tracking_active',
            'latitude',
            'longitude',
            'updated_at',
            'status',
            'order_status',
            'kurir_name',
        ]);
    }

    /**
     * Customer mendapat status offline ketika tidak ada data lokasi.
     */
    public function test_customer_gets_offline_when_no_location(): void
    {
        $order = $this->createOrder(OrderStatus::DIJEMPUT);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->getJson("/api/customer/orders/{$order->id}/tracking");

        $response->assertStatus(200);
        $response->assertJson([
            'tracking_active' => true,
            'latitude'        => null,
            'longitude'       => null,
            'status'          => 'offline',
        ]);
    }

    /**
     * Tracking tidak aktif untuk order yang statusnya non-trackable (misal: diproses).
     */
    public function test_tracking_inactive_for_non_trackable_status(): void
    {
        $order = $this->createOrder(OrderStatus::DIPROSES);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->getJson("/api/customer/orders/{$order->id}/tracking");

        $response->assertStatus(200);
        $response->assertJson([
            'tracking_active' => false,
            'order_status'    => 'diproses',
        ]);
    }
}
