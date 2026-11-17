<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Review;
use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisputeAndReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $owner;
    protected User $kurir;
    protected User $adminOwner;
    protected Toko $toko;
    protected Tarif $tarif;
    protected Order $completedOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        // Setup Customer
        $this->customer = User::factory()->create(['name' => 'Budi Customer']);
        $this->customer->assignRole(UserRole::CUSTOMER->value);

        // Setup Toko & Owner
        $this->owner = User::factory()->create(['name' => 'Siti Owner']);
        $this->owner->assignRole(UserRole::OWNER_TOKO->value);

        $this->toko = Toko::create([
            'nama' => 'Laundry Berkah',
            'owner_id' => $this->owner->id,
            'alamat' => 'Jl. Mawar No 10',
            'status' => 'approved',
        ]);

        $this->tarif = Tarif::create([
            'toko_id' => $this->toko->id,
            'jenis_layanan' => 'Cuci Komplit',
            'harga' => 8000,
        ]);

        // Setup Kurir
        $this->kurir = User::factory()->create(['name' => 'Doni Kurir']);
        $this->kurir->assignRole(UserRole::KURIR->value);

        // Setup Admin Owner
        $this->adminOwner = User::factory()->create(['name' => 'Admin Owner']);
        $this->adminOwner->assignRole(UserRole::ADMIN_OWNER->value);

        // Setup Completed Order
        $this->completedOrder = Order::create([
            'customer_id' => $this->customer->id,
            'toko_id' => $this->toko->id,
            'kurir_id' => $this->kurir->id,
            'tarif_id' => $this->tarif->id,
            'alamat_jemput' => 'Jl. Melati No 5',
            'status' => OrderStatus::SELESAI->value,
            'berat' => 3.5,
            'total_biaya' => 28000,
        ]);
    }

    /**
     * 1. Customer dapat memberikan ulasan terpisah untuk toko dan kurir setelah order selesai.
     */
    public function test_customer_can_review_completed_order_for_both_toko_and_kurir(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $payload = [
            'toko_rating' => 5,
            'toko_komentar' => 'Cucian sangat bersih dan wangi!',
            'kurir_rating' => 4,
            'kurir_komentar' => 'Kurir ramah dan tepat waktu.',
        ];

        $response = $this->withHeaders([
            'X-Active-Role' => UserRole::CUSTOMER->value,
            'Accept' => 'application/json',
        ])->postJson("/api/customer/orders/{$this->completedOrder->id}/review", $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'reviews' => ['toko', 'kurir']]);

        // Pastikan review tersimpan terpisah di database
        $this->assertDatabaseHas('reviews', [
            'order_id' => $this->completedOrder->id,
            'toko_id' => $this->toko->id,
            'rating' => 5,
            'komentar' => 'Cucian sangat bersih dan wangi!',
        ]);

        $this->assertDatabaseHas('reviews', [
            'order_id' => $this->completedOrder->id,
            'kurir_id' => $this->kurir->id,
            'rating' => 4,
            'komentar' => 'Kurir ramah dan tepat waktu.',
        ]);
    }

    /**
     * 2. Customer TIDAK BISA memberikan ulasan sebelum order berstatus 'selesai'.
     */
    public function test_customer_cannot_review_incomplete_order(): void
    {
        $activeOrder = Order::create([
            'customer_id' => $this->customer->id,
            'toko_id' => $this->toko->id,
            'status' => OrderStatus::DIPROSES->value,
            'alamat_jemput' => 'Jl. Kenanga 12',
        ]);

        Sanctum::actingAs($this->customer, ['*']);

        $response = $this->withHeaders([
            'X-Active-Role' => UserRole::CUSTOMER->value,
            'Accept' => 'application/json',
        ])->postJson("/api/customer/orders/{$activeOrder->id}/review", [
            'toko_rating' => 5,
            'toko_komentar' => 'Belum selesai tapi review dulu',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => "Ulasan hanya dapat diberikan setelah pesanan berstatus 'selesai'."]);
    }

    /**
     * 3. Satu review per order (Customer TIDAK BISA review ulang pesanan yang sudah diulas).
     */
    public function test_customer_cannot_review_order_more_than_once(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $headers = [
            'X-Active-Role' => UserRole::CUSTOMER->value,
            'Accept' => 'application/json',
        ];

        // Review pertama: Berhasil
        $res1 = $this->withHeaders($headers)->postJson("/api/customer/orders/{$this->completedOrder->id}/review", [
            'toko_rating' => 5,
            'toko_komentar' => 'Pertama kali ulas',
        ]);
        $res1->assertStatus(201);

        // Review kedua: Ditolak dengan 422
        $res2 = $this->withHeaders($headers)->postJson("/api/customer/orders/{$this->completedOrder->id}/review", [
            'toko_rating' => 1,
            'toko_komentar' => 'Mencoba ulas lagi',
        ]);
        $res2->assertStatus(422)
            ->assertJsonFragment(['message' => 'Pesanan ini sudah pernah diulas. Ulasan hanya dapat diberikan satu kali per pesanan.']);
    }

    /**
     * 4. Customer dapat membuka dispute dan aksinya tercatat di audit_logs.
     */
    public function test_customer_can_open_dispute_and_audit_log_is_recorded(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $payload = [
            'alasan' => 'berat_tidak_sesuai',
            'deskripsi' => 'Berat cucian saya hanya sekitar 2 kg, tetapi dihitung 3.5 kg.',
        ];

        $response = $this->withHeaders([
            'X-Active-Role' => UserRole::CUSTOMER->value,
            'Accept' => 'application/json',
        ])->postJson("/api/orders/{$this->completedOrder->id}/dispute", $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'open'])
            ->assertJsonFragment(['alasan' => 'berat_tidak_sesuai']);

        $this->assertDatabaseHas('disputes', [
            'order_id' => $this->completedOrder->id,
            'dibuka_oleh' => $this->customer->id,
            'alasan' => 'berat_tidak_sesuai',
            'status' => 'open',
        ]);

        // Verifikasi tercatat di Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->customer->id,
            'action' => 'CREATE_DISPUTE',
            'target_type' => Dispute::class,
        ]);
    }

    /**
     * 5. Toko dapat membuka dispute jika terdapat kendala pakaian.
     */
    public function test_toko_owner_can_open_dispute_and_audit_log_is_recorded(): void
    {
        Sanctum::actingAs($this->owner, ['*']);

        $payload = [
            'alasan' => 'barang_rusak',
            'deskripsi' => 'Terdapat pakaian yang sudah robek dan luntur sebelum mulai dicuci oleh pihak toko.',
        ];

        $response = $this->withHeaders([
            'X-Active-Role' => UserRole::OWNER_TOKO->value,
            'Accept' => 'application/json',
        ])->postJson("/api/orders/{$this->completedOrder->id}/dispute", $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['status' => 'open'])
            ->assertJsonFragment(['alasan' => 'barang_rusak']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->owner->id,
            'action' => 'CREATE_DISPUTE',
            'target_type' => Dispute::class,
        ]);
    }

    /**
     * 6. User lain yang tidak terkait tidak bisa membuka dispute (HTTP 403).
     */
    public function test_unrelated_user_cannot_open_dispute(): void
    {
        $stranger = User::factory()->create(['name' => 'Orang Lain']);
        $stranger->assignRole(UserRole::CUSTOMER->value);
        Sanctum::actingAs($stranger, ['*']);

        $response = $this->withHeaders([
            'X-Active-Role' => UserRole::CUSTOMER->value,
            'Accept' => 'application/json',
        ])->postJson("/api/orders/{$this->completedOrder->id}/dispute", [
            'alasan' => 'iseng',
            'deskripsi' => 'Mencoba membuka dispute pada order orang lain.',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 7. Tidak bisa membuka lebih dari satu dispute aktif pada order yang sama.
     */
    public function test_cannot_open_duplicate_active_dispute_on_same_order(): void
    {
        Dispute::create([
            'order_id' => $this->completedOrder->id,
            'dibuka_oleh' => $this->customer->id,
            'alasan' => 'berat_tidak_sesuai',
            'deskripsi' => 'Dispute pertama masih aktif.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($this->customer, ['*']);

        $response = $this->withHeaders([
            'X-Active-Role' => UserRole::CUSTOMER->value,
            'Accept' => 'application/json',
        ])->postJson("/api/orders/{$this->completedOrder->id}/dispute", [
            'alasan' => 'barang_rusak',
            'deskripsi' => 'Dispute kedua harus ditolak karena masih ada yang open.',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Pesanan ini sudah memiliki dispute yang sedang aktif dan dalam penanganan admin.']);
    }

    /**
     * 8. Admin Owner dapat melihat dan memperbarui status dispute: open -> in_review -> resolved/rejected.
     */
    public function test_admin_owner_can_update_dispute_status_with_audit_logs(): void
    {
        $dispute = Dispute::create([
            'order_id' => $this->completedOrder->id,
            'dibuka_oleh' => $this->customer->id,
            'alasan' => 'berat_tidak_sesuai',
            'deskripsi' => 'Berat tidak cocok.',
            'status' => 'open',
        ]);

        Sanctum::actingAs($this->adminOwner, ['*']);
        $headers = [
            'X-Active-Role' => UserRole::ADMIN_OWNER->value,
            'Accept' => 'application/json',
        ];

        // 1. Ubah ke in_review
        $resInReview = $this->withHeaders($headers)->patchJson("/api/platform/disputes/{$dispute->id}", [
            'status' => 'in_review',
        ]);
        $resInReview->assertStatus(200)
            ->assertJsonPath('dispute.status', 'in_review');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminOwner->id,
            'action' => 'IN_REVIEW_DISPUTE',
            'target_id' => $dispute->id,
        ]);

        // 2. Resolve dispute: wajib ada catatan resolusi (jika kosong harus gagal 422)
        $resFailResolve = $this->withHeaders($headers)->patchJson("/api/platform/disputes/{$dispute->id}", [
            'status' => 'resolved',
            'resolusi' => '',
        ]);
        $resFailResolve->assertStatus(422);

        // 3. Resolve dispute dengan catatan resolusi valid: Berhasil & tercatat di audit log
        $resSuccessResolve = $this->withHeaders($headers)->patchJson("/api/platform/disputes/{$dispute->id}", [
            'status' => 'resolved',
            'resolusi' => 'Toko telah dikonfirmasi dan telah memberikan potongan biaya kompensasi senilai Rp 10.000.',
        ]);
        $resSuccessResolve->assertStatus(200)
            ->assertJsonPath('dispute.status', 'resolved')
            ->assertJsonPath('dispute.resolusi', 'Toko telah dikonfirmasi dan telah memberikan potongan biaya kompensasi senilai Rp 10.000.');

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->adminOwner->id,
            'action' => 'RESOLVE_DISPUTE',
            'target_id' => $dispute->id,
        ]);
    }
}
