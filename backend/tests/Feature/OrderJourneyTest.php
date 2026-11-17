<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use Database\Seeders\MarketplaceSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $owner;
    protected User $kurir;
    protected Toko $toko;
    protected Tarif $tarif;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(MarketplaceSeeder::class);

        // Ambil data dari seeder
        $this->customer = User::whereHas('roles', fn($q) => $q->where('name', UserRole::CUSTOMER->value))->first();
        $this->owner = User::whereHas('roles', fn($q) => $q->where('name', UserRole::OWNER_TOKO->value))->first();
        
        // Buat kurir
        $this->kurir = User::factory()->create(['name' => 'Kurir Kilat']);
        $this->kurir->assignRole(UserRole::KURIR->value);

        $this->toko = Toko::where('owner_id', $this->owner->id)->first();
        $this->tarif = Tarif::where('toko_id', $this->toko->id)->first();
    }

    /**
     * Uji alur order lengkap dari pemesanan hingga selesai & ulasan.
     */
    public function test_complete_order_journey_flow(): void
    {
        // 1. Customer buat order
        $createRes = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->postJson('/api/customer/orders', [
                'toko_id'       => $this->toko->id,
                'tarif_id'      => $this->tarif->id,
                'alamat_jemput' => 'Jl. Tebet Barat No. 10',
                'catatan'       => 'Pagar hitam, tolong pisahkan baju putih.',
            ]);

        $createRes->assertStatus(201);
        $orderId = $createRes->json('order.id');
        $this->assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => OrderStatus::MENUNGGU_KONFIRMASI->value,
        ]);

        // Verifikasi cancel window saat masih awal (Customer BISA cancel)
        $order = Order::find($orderId);
        $this->assertTrue($order->canCancelByCustomer());

        // 2. Toko konfirmasi order -> status menunggu_kurir
        $confirmRes = $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->postJson("/api/owner/orders/{$orderId}/confirm");

        $confirmRes->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => OrderStatus::MENUNGGU_KURIR->value,
        ]);

        // 3. Kurir ambil tugas
        $acceptRes = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson("/api/kurir/tasks/{$orderId}/accept");

        $acceptRes->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'       => $orderId,
            'kurir_id' => $this->kurir->id,
        ]);

        // 4. Kurir jemput pakaian di alamat customer -> status dijemput
        $pickupRes = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson("/api/kurir/tasks/{$orderId}/status", [
                'status' => OrderStatus::DIJEMPUT->value,
            ]);

        $pickupRes->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => OrderStatus::DIJEMPUT->value,
        ]);

        // 5. Uji Cancel Window: Customer coba cancel setelah pakaian dijemput -> HARUS DITOLAK
        $cancelTryRes = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->postJson("/api/customer/orders/{$orderId}/cancel", [
                'alasan' => 'Ingin ganti jadwal',
            ]);

        $cancelTryRes->assertStatus(422);
        $cancelTryRes->assertJsonFragment(['error' => 'INVALID_ORDER_TRANSITION']);

        // 6. Kurir tiba di toko laundry -> status di_toko
        $arriveRes = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson("/api/kurir/tasks/{$orderId}/status", [
                'status' => OrderStatus::DI_TOKO->value,
            ]);

        $arriveRes->assertStatus(200);

        // 7. Role Isolation Test: Kurir coba set status diproses -> HARUS DITOLAK
        $illegalRoleRes = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson("/api/kurir/tasks/{$orderId}/status", [
                'status' => OrderStatus::DIPROSES->value,
            ]);

        $illegalRoleRes->assertStatus(422);

        // 8. Staff / Owner Toko timbang berat -> sistem hitung total biaya otomatis
        $berat = 3.5;
        $expectedTotal = round($berat * (float) $this->tarif->harga, 2);

        $weighRes = $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->postJson("/api/owner/orders/{$orderId}/weigh", [
                'berat' => $berat,
            ]);

        $weighRes->assertStatus(200);
        $this->assertEquals($expectedTotal, (float) $weighRes->json('total_biaya'));
        $this->assertDatabaseHas('orders', [
            'id'          => $orderId,
            'status'      => OrderStatus::MENUNGGU_PEMBAYARAN_INFO->value,
            'berat'       => $berat,
            'total_biaya' => $expectedTotal,
        ]);

        // 9. Toko mulai proses cuci -> status diproses
        $processRes = $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->postJson("/api/owner/orders/{$orderId}/status", [
                'status' => OrderStatus::DIPROSES->value,
            ]);

        $processRes->assertStatus(200);

        // 10. Toko selesai cuci & packing -> status siap_diantar
        $readyRes = $this->actingAs($this->owner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->postJson("/api/owner/orders/{$orderId}/status", [
                'status' => OrderStatus::SIAP_DIANTAR->value,
            ]);

        $readyRes->assertStatus(200);

        // 11. Kurir antar kembali ke customer -> status diantar
        $deliveryRes = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson("/api/kurir/tasks/{$orderId}/status", [
                'status' => OrderStatus::DIANTAR->value,
            ]);

        $deliveryRes->assertStatus(200);

        // 12. Kurir serah terima & terima uang tunai COD -> status cod_selesai
        $codRes = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson("/api/kurir/tasks/{$orderId}/status", [
                'status' => OrderStatus::COD_SELESAI->value,
            ]);

        $codRes->assertStatus(200);

        // 13. Kurir selesaikan pesanan -> status selesai
        $doneRes = $this->actingAs($this->kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->postJson("/api/kurir/tasks/{$orderId}/status", [
                'status' => OrderStatus::SELESAI->value,
            ]);

        $doneRes->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => OrderStatus::SELESAI->value,
        ]);

        // 14. Customer kirim review terpisah untuk toko & kurir
        $reviewRes = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->postJson("/api/customer/orders/{$orderId}/review", [
                'toko_rating'    => 5,
                'toko_komentar'  => 'Cucian sangat wangi dan rapi!',
                'kurir_rating'   => 5,
                'kurir_komentar' => 'Kurir ramah dan cepat sampai.',
            ]);

        $reviewRes->assertStatus(201);

        // Verifikasi tabel reviews memiliki ulasan terpisah untuk toko dan kurir
        $this->assertDatabaseHas('reviews', [
            'order_id' => $orderId,
            'toko_id'  => $this->toko->id,
            'rating'   => 5,
        ]);
        $this->assertDatabaseHas('reviews', [
            'order_id' => $orderId,
            'kurir_id' => $this->kurir->id,
            'rating'   => 5,
        ]);

        // 15. Verifikasi riwayat status lengkap terekam di order_status_histories
        $histories = Order::find($orderId)->statusHistories;
        $this->assertGreaterThanOrEqual(10, $histories->count());
    }

    /**
     * Uji pembatalan pesanan saat masih dalam window cancel (menunggu_konfirmasi).
     */
    public function test_customer_can_cancel_within_window(): void
    {
        $order = Order::create([
            'customer_id'   => $this->customer->id,
            'toko_id'       => $this->toko->id,
            'tarif_id'      => $this->tarif->id,
            'status'        => OrderStatus::MENUNGGU_KONFIRMASI,
            'alamat_jemput' => 'Alamat Jemput Test',
            'metode_bayar'  => 'CASH',
        ]);

        $cancelRes = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->postJson("/api/customer/orders/{$order->id}/cancel", [
                'alasan' => 'Ada keperluan mendadak',
            ]);

        $cancelRes->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::DIBATALKAN->value,
        ]);
    }
}
