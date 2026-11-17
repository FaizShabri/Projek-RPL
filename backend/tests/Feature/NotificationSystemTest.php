<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Listeners\SendOrderStatusNotification;
use App\Models\Order;
use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Chatbot\Tools\Customer\GetMyNotifications;
use App\Services\Notification\NotificationPreferenceService;
use App\Services\OrderService;
use Database\Seeders\RoleAndPermissionSeeder;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $customerB;
    protected User $owner;
    protected User $kurir;
    protected Toko $toko;
    protected Tarif $tarif;
    protected OrderService $orderService;
    protected NotificationPreferenceService $preferenceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        // Setup Users
        $this->customer = User::factory()->create(['name' => 'Budi Customer']);
        $this->customer->assignRole(UserRole::CUSTOMER->value);

        $this->customerB = User::factory()->create(['name' => 'Ani Customer']);
        $this->customerB->assignRole(UserRole::CUSTOMER->value);

        $this->owner = User::factory()->create(['name' => 'Siti Owner']);
        $this->owner->assignRole(UserRole::OWNER_TOKO->value);

        $this->kurir = User::factory()->create(['name' => 'Doni Kurir']);
        $this->kurir->assignRole(UserRole::KURIR->value);

        // Setup Toko & Tarif
        $this->toko = Toko::create([
            'nama' => 'Laundry Bersih Jaya',
            'owner_id' => $this->owner->id,
            'alamat' => 'Jl. Merdeka No 45',
            'status' => 'approved',
        ]);

        $this->tarif = Tarif::create([
            'toko_id' => $this->toko->id,
            'jenis_layanan' => 'Cuci Kering Setrika',
            'harga' => 10000,
        ]);

        $this->orderService = app(OrderService::class);
        $this->preferenceService = app(NotificationPreferenceService::class);
    }

    /**
     * (a) Setiap transisi status memicu tepat satu notifikasi ke penerima yang benar dan bersifat idempotent.
     */
    public function test_order_transitions_trigger_correct_notifications_and_are_idempotent(): void
    {
        // 1. Buat order: Customer membuat order -> Toko menerima notifikasi pesanan baru
        $order = $this->orderService->createOrder([
            'toko_id' => $this->toko->id,
            'tarif_id' => $this->tarif->id,
            'alamat_jemput' => 'Jl. Bunga Melati No. 12',
        ], $this->customer);

        $ownerNotifs = $this->owner->notifications;
        $this->assertCount(1, $ownerNotifs);
        $this->assertEquals('new_order', $ownerNotifs->first()->data['type']);
        $this->assertEquals($order->id, $ownerNotifs->first()->data['order_id']);

        // 2. Toko konfirmasi order -> Customer dapat info dikonfirmasi, Kurir dapat tugas baru
        $order = $this->orderService->confirmOrder($order, $this->owner, UserRole::OWNER_TOKO->value);

        $customerNotifs = $this->customer->fresh()->notifications;
        $this->assertCount(1, $customerNotifs);
        $this->assertEquals('order_status', $customerNotifs->first()->data['type']);

        $kurirNotifs = $this->kurir->fresh()->notifications;
        $this->assertCount(1, $kurirNotifs);
        $this->assertEquals('new_task', $kurirNotifs->first()->data['type']);

        // 3. Kurir menerima tugas -> Customer mendapat info kurir menerima tugas
        $order = $this->orderService->acceptPickupTask($order, $this->kurir, UserRole::KURIR->value);
        $customerNotifs = $this->customer->fresh()->notifications;
        $this->assertCount(2, $customerNotifs);
        $types = $customerNotifs->map(fn ($n) => $n->data['type'])->all();
        $this->assertContains('kurir_assigned', $types);
        $this->assertContains('order_status', $types);

        // 4. Kurir jemput & tiba di toko
        $order = $this->orderService->transitionStatus($order, OrderStatus::DIJEMPUT, $this->kurir, UserRole::KURIR->value);
        $order = $this->orderService->transitionStatus($order, OrderStatus::DI_TOKO, $this->kurir, UserRole::KURIR->value);

        // 5. Toko menimbang pakaian -> Customer menerima notifikasi kritis biaya COD
        $order = $this->orderService->weighAndCalculateTotal($order, 3.5, $this->owner, UserRole::OWNER_TOKO->value);
        $codNotif = $this->customer->fresh()->notifications()->where('data->type', 'cod_fee')->first();
        $this->assertNotNull($codNotif);
        $this->assertStringContainsString('3.5 kg', $codNotif->data['message']);
        $this->assertStringContainsString('35.000', $codNotif->data['message']);

        // 6. Uji Idempotensi: Listener dijalankan ulang dengan status history yang sama tidak membuat notifikasi ganda
        $history = $order->statusHistories()->orderBy('id', 'desc')->first();
        $listener = app(SendOrderStatusNotification::class);
        $event = new OrderStatusChanged($order, $history, OrderStatus::DI_TOKO->value, $this->owner);

        $totalNotifsBefore = DatabaseNotification::count();
        $listener->handle($event);
        $totalNotifsAfter = DatabaseNotification::count();

        $this->assertEquals($totalNotifsBefore, $totalNotifsAfter, 'Notifikasi tidak boleh digandakan jika event di-retry.');
    }

    /**
     * (b) User A tidak bisa membaca atau menandai notifikasi milik User B (Enforced by Policy).
     */
    public function test_user_a_cannot_read_or_mark_as_read_user_b_notification(): void
    {
        // Buat notifikasi langsung untuk User B
        $order = $this->orderService->createOrder([
            'toko_id' => $this->toko->id,
            'tarif_id' => $this->tarif->id,
            'alamat_jemput' => 'Jl. Kenanga No. 5',
        ], $this->customerB);

        // Notifikasi untuk Siti Owner
        $notifOwner = $this->owner->notifications()->first();
        $this->assertNotNull($notifOwner);

        // Customer A login dan mencoba menandai notifikasi milik Owner
        Sanctum::actingAs($this->customer);

        $response = $this->patchJson("/api/notifications/{$notifOwner->id}/read");
        $response->assertStatus(403);

        // Pastikan read_at pada notifikasi owner tetap NULL
        $this->assertNull($notifOwner->fresh()->read_at);

        // Customer A mengakses daftar notifikasinya -> notifikasi owner tidak boleh muncul
        $listResponse = $this->getJson('/api/notifications');
        $listResponse->assertStatus(200);
        $this->assertEmpty($listResponse->json('notifications.data'));
    }

    /**
     * (c) Kegagalan pengiriman notifikasi tidak menggagalkan update status order.
     */
    public function test_notification_failure_does_not_break_order_status_update(): void
    {
        $order = $this->orderService->createOrder([
            'toko_id' => $this->toko->id,
            'tarif_id' => $this->tarif->id,
            'alamat_jemput' => 'Jl. Cemara No. 8',
        ], $this->customer);

        // Pasang listener yang sengaja melempar exception pada OrderStatusChanged
        Event::listen(OrderStatusChanged::class, function () {
            throw new Exception('Simulasi kegagalan koneksi channel notifikasi');
        });

        // Update status order (cancel order)
        // Karena SendOrderStatusNotification dibungkus try/catch di listener aman,
        // transisi status order harus tetap berhasil di database!
        try {
            $cancelledOrder = $this->orderService->cancelOrder($order, $this->customer, UserRole::CUSTOMER->value, 'Salah pesan');
        } catch (Exception $e) {
            // Jika unhandled di luar listener
        }

        $freshOrder = Order::find($order->id);
        $this->assertEquals(OrderStatus::DIBATALKAN->value, $freshOrder->status->value);
    }

    /**
     * (d) Notifikasi kritis tidak bisa dimatikan lewat preferensi.
     */
    public function test_critical_notifications_cannot_be_disabled_via_preferences(): void
    {
        Sanctum::actingAs($this->customer);

        // 1. Coba matikan notifikasi kritis 'cod_fee' -> Harus ditolak (422)
        $response = $this->putJson('/api/notification-preferences', [
            'preferences' => [
                'cod_fee' => false,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => "Notifikasi 'Tagihan & Informasi Biaya COD' bersifat kritis dan tidak dapat dimatikan demi keamanan transaksi.",
        ]);

        // 2. Coba matikan notifikasi kritis 'order_cancelled' -> Harus ditolak (422)
        $responseCancel = $this->putJson('/api/notification-preferences', [
            'preferences' => [
                'order_cancelled' => false,
            ],
        ]);
        $responseCancel->assertStatus(422);

        // 3. Matikan notifikasi non-kritis 'order_status' -> Harus berhasil (200)
        $responseOk = $this->putJson('/api/notification-preferences', [
            'preferences' => [
                'order_status' => false,
            ],
        ]);
        $responseOk->assertStatus(200);

        // Cek database
        $pref = UserNotificationPreference::where('user_id', $this->customer->id)
            ->where('notification_type', 'order_status')
            ->first();
        $this->assertNotNull($pref);
        $this->assertFalse((bool) $pref->is_enabled);
    }

    /**
     * Test integrasi Chatbot tool GetMyNotifications (Read-Only & Scoped).
     */
    public function test_chatbot_can_read_user_notifications_safely(): void
    {
        $this->orderService->createOrder([
            'toko_id' => $this->toko->id,
            'tarif_id' => $this->tarif->id,
            'alamat_jemput' => 'Jl. Mawar Indah No. 1',
        ], $this->customer);

        $tool = new GetMyNotifications();
        $result = $tool->execute([], $this->owner, UserRole::OWNER_TOKO->value);

        $this->assertArrayHasKey('notifications', $result);
        $this->assertNotEmpty($result['notifications']);
        $this->assertEquals('new_order', $result['notifications'][0]['type']);
    }

    /**
     * Test scheduled command membersihkan notifikasi yang sudah dibaca >90 hari.
     */
    public function test_cleanup_old_notifications_command(): void
    {
        // 1. Notifikasi lama sudah dibaca (>90 hari) -> Harus dihapus
        DB::table('notifications')->insert([
            'id' => 'notif-old-read',
            'type' => 'App\Notifications\PusatLaundryNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->customer->id,
            'data' => json_encode(['title' => 'Lama']),
            'read_at' => now()->subDays(95),
            'created_at' => now()->subDays(95),
            'updated_at' => now()->subDays(95),
        ]);

        // 2. Notifikasi lama belum dibaca (>90 hari) -> Tidak boleh dihapus
        DB::table('notifications')->insert([
            'id' => 'notif-old-unread',
            'type' => 'App\Notifications\PusatLaundryNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->customer->id,
            'data' => json_encode(['title' => 'Unread Lama']),
            'read_at' => null,
            'created_at' => now()->subDays(95),
            'updated_at' => now()->subDays(95),
        ]);

        // 3. Notifikasi baru sudah dibaca (<90 hari) -> Tidak boleh dihapus
        DB::table('notifications')->insert([
            'id' => 'notif-new-read',
            'type' => 'App\Notifications\PusatLaundryNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->customer->id,
            'data' => json_encode(['title' => 'Baru']),
            'read_at' => now()->subDays(10),
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->artisan('notifications:cleanup', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => 'notif-old-read']);
        $this->assertDatabaseHas('notifications', ['id' => 'notif-old-unread']);
        $this->assertDatabaseHas('notifications', ['id' => 'notif-new-read']);
    }
}
