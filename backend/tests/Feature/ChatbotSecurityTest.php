<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ChatLog;
use App\Models\Order;
use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\ChatbotToolRegistry;
use App\Services\Chatbot\SensitiveDataMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class ChatbotSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $ownerA;
    protected User $ownerB;
    protected User $adminOwner;
    protected User $platformOwner;

    protected Toko $tokoA;
    protected Toko $tokoB;
    protected Order $orderA;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat roles
        Role::findOrCreate(UserRole::CUSTOMER->value);
        Role::findOrCreate(UserRole::OWNER_TOKO->value);
        Role::findOrCreate(UserRole::ADMIN_TOKO->value);
        Role::findOrCreate(UserRole::ADMIN_OWNER->value);
        Role::findOrCreate(UserRole::PLATFORM_OWNER->value);
        Role::findOrCreate(UserRole::KURIR->value);

        // Buat Users
        $this->customer = User::factory()->create(['name' => 'Budi Customer', 'email' => 'budi@example.com']);
        $this->customer->assignRole(UserRole::CUSTOMER->value);

        $this->ownerA = User::factory()->create(['name' => 'Owner Toko A', 'email' => 'owner.a@example.com']);
        $this->ownerA->assignRole(UserRole::OWNER_TOKO->value);

        $this->ownerB = User::factory()->create(['name' => 'Owner Toko B', 'email' => 'owner.b@example.com']);
        $this->ownerB->assignRole(UserRole::OWNER_TOKO->value);

        $this->adminOwner = User::factory()->create(['name' => 'Admin Owner', 'email' => 'admin@example.com']);
        $this->adminOwner->assignRole(UserRole::ADMIN_OWNER->value);

        $this->platformOwner = User::factory()->create(['name' => 'Platform Owner', 'email' => 'super@example.com']);
        $this->platformOwner->assignRole(UserRole::PLATFORM_OWNER->value);

        // Toko A & B
        $this->tokoA = Toko::create([
            'owner_id' => $this->ownerA->id,
            'nama' => 'Laundry Berkah A',
            'alamat' => 'Jl. Mawar No 1',
            'status' => 'approved',
        ]);

        $this->tokoB = Toko::create([
            'owner_id' => $this->ownerB->id,
            'nama' => 'Laundry Sukses B',
            'alamat' => 'Jl. Melati No 2',
            'status' => 'approved',
        ]);

        $tarifA = Tarif::create([
            'toko_id' => $this->tokoA->id,
            'jenis_layanan' => 'Cuci Komplit',
            'harga' => 8000,
        ]);

        $this->orderA = Order::create([
            'customer_id' => $this->customer->id,
            'toko_id' => $this->tokoA->id,
            'tarif_id' => $tarifA->id,
            'status' => OrderStatus::MENUNGGU_KONFIRMASI,
            'alamat_jemput' => 'Jl. Customer No 10',
            'metode_bayar' => 'CASH',
        ]);
    }

    /**
     * (a) Customer TIDAK BISA memancing chatbot untuk mengeksekusi aksi state-changing.
     * Semua aksi mutasi (cancel order, dsb) ditolak dan diarahkan ke form resmi.
     */
    public function test_customer_cannot_execute_state_changing_actions(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', 'customer')
            ->postJson('/api/chatbot/message', [
                'message' => 'Tolong batalkan pesanan saya nomor #' . $this->orderA->id . ' sekarang juga!',
            ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        // Pastikan status order di database TIDAK BERUBAH
        $this->assertEquals(OrderStatus::MENUNGGU_KONFIRMASI->value, $this->orderA->fresh()->status->value);

        // Jawaban AI harus menegaskan tidak bisa cancel langsung dan mengarahkan ke menu
        $this->assertStringContainsString('TIDAK BISA', $reply);
        $this->assertStringContainsString('Pesanan Saya', $reply);

        // Pastikan TIDAK ADA tool mutasi yang dijalankan
        $toolsUsed = $response->json('tools_used');
        $this->assertNotContains('cancel_order', $toolsUsed);
        $this->assertNotContains('delete_order', $toolsUsed);
    }

    /**
     * (b) Admin Owner TIDAK BISA memancing chatbot membocorkan data komisi/keuangan platform,
     * sedangkan Platform Owner BISA melihat data komisi.
     */
    public function test_admin_owner_cannot_access_commission_data(): void
    {
        // 1. Admin Owner bertanya tentang komisi
        $responseAdmin = $this->actingAs($this->adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', 'admin_owner')
            ->postJson('/api/chatbot/message', [
                'message' => 'Berapa total komisi platform yang terkumpul bulan ini?',
            ]);

        $responseAdmin->assertStatus(200);
        $adminReply = $responseAdmin->json('reply');

        // Admin Owner HARUS ditolak untuk akses data komisi/keuangan
        $this->assertStringContainsString('tidak memiliki akses', $adminReply);
        $this->assertStringContainsString('Platform Owner', $adminReply);

        // 2. Eksekusi tool langsung get_platform_analytics oleh admin_owner:
        // response keuangan_platform harus 'RESTRICTED'
        $registry = new ChatbotToolRegistry();
        $analyticsAdmin = $registry->executeTool('get_platform_analytics', [], $this->adminOwner, 'admin_owner');

        $this->assertEquals('RESTRICTED', $analyticsAdmin['keuangan_platform']['status']);

        // 3. Bandingkan dengan Platform Owner yang BERHAK melihat keuangan
        $analyticsPlatform = $registry->executeTool('get_platform_analytics', [], $this->platformOwner, 'platform_owner');
        $this->assertArrayHasKey('gmv_omzet_kotor', $analyticsPlatform['keuangan_platform']);
        $this->assertArrayHasKey('komisi_platform_estimasi', $analyticsPlatform['keuangan_platform']);
    }

    /**
     * (c) Chatbot role satu tidak bisa dipakai untuk mengintip data role lain
     * (Owner Toko A TIDAK BISA melihat data toko Toko B lewat chatbot).
     */
    public function test_owner_toko_a_cannot_peek_at_toko_b_data(): void
    {
        $registry = new ChatbotToolRegistry();

        // Owner Toko A mencoba passing toko_id milik Toko B
        $result = $registry->executeTool('get_toko_performance', [
            'toko_id' => $this->tokoB->id,
        ], $this->ownerA, 'owner_toko');

        // Harus mengembalikan error karena toko B bukan milik Owner A
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('tidak ditemukan', $result['error']);

        // Ketika tidak mengirim toko_id, secara otomatis hanya mengambil Toko A
        $myPerf = $registry->executeTool('get_toko_performance', [], $this->ownerA, 'owner_toko');
        $this->assertEquals($this->tokoA->id, $myPerf['toko']['id']);
        $this->assertEquals($this->tokoA->nama, $myPerf['toko']['nama']);
    }

    /**
     * (d) Verifikasi Per-Tool Role Authorization:
     * Customer TIDAK BISA mengeksekusi tool milik Admin atau Owner Toko.
     */
    public function test_customer_cannot_call_unauthorized_tools(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $registry = new ChatbotToolRegistry();
        // Customer mencoba panggil tool admin 'get_platform_disputes'
        $registry->executeTool('get_platform_disputes', [], $this->customer, 'customer');
    }

    /**
     * (e) Audit & Logging dengan Sensitive Data Masking.
     * Pastikan nomor HP, email, dsb di-mask sebelum masuk ke chat_logs.
     */
    public function test_chat_logs_record_and_mask_sensitive_data(): void
    {
        $sensitiveMessage = 'Tolong hubungi saya di 081234567890 atau email test.user@gmail.com terkait laundry saya.';

        $response = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', 'customer')
            ->postJson('/api/chatbot/message', [
                'message' => $sensitiveMessage,
            ]);

        $response->assertStatus(200);

        // Cek log di tabel chat_logs
        $log = ChatLog::where('user_id', $this->customer->id)->latest('id')->first();
        $this->assertNotNull($log);

        // Nomor telepon dan email HARUS ter-mask
        $this->assertStringNotContainsString('081234567890', $log->user_message);
        $this->assertStringNotContainsString('test.user@gmail.com', $log->user_message);
        $this->assertStringContainsString('[PHONE_MASKED]', $log->user_message);
        $this->assertStringContainsString('[EMAIL_MASKED]', $log->user_message);
    }

    /**
     * (f) Cek status pesanan mengembalikan kalimat bahasa Indonesia alami yang rapi (Bebas JSON mentah).
     */
    public function test_check_order_status_returns_clean_natural_indonesian_without_raw_json(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', 'customer')
            ->postJson('/api/chatbot/message', [
                'message' => 'Cek status pesanan aktif saya',
            ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');

        // Balasan TIDAK BOLEH mengandung kurung kurawal JSON mentah
        $this->assertStringNotContainsString('{"message":', $reply);
        $this->assertStringNotContainsString('{"orders":', $reply);
        $this->assertStringNotContainsString('"status_raw":', $reply);

        // Balasan harus menyebut nomor order dan nama toko dengan format rapi
        $this->assertStringContainsString("Order #{$this->orderA->id}", $reply);
        $this->assertStringContainsString($this->tokoA->nama, $reply);
        $this->assertTrue($response->json('is_demo'));
    }

    /**
     * (g) Multi-turn sanitasi: pesan assistant di awal riwayat dibersihkan & role bergantian ketat.
     */
    public function test_multiturn_conversation_sanitizes_history_properly(): void
    {
        $anthropicService = app(\App\Services\Chatbot\AnthropicService::class);

        // Riwayat kotor: pesan pertama assistant, ada user berturut-turut
        $dirtyHistory = [
            ['role' => 'assistant', 'content' => 'Halo Budi, ada yang bisa saya bantu?'],
            ['role' => 'user', 'content' => 'Toko mana yang buka?'],
            ['role' => 'user', 'content' => 'Yang ratingnya bagus ya.'],
            ['role' => 'assistant', 'content' => 'Berikut rekomendasinya...'],
        ];

        $sanitized = $anthropicService->sanitizeMessages($dirtyHistory, 'Terima kasih infonya');

        // 1. Pesan pertama WAJIB user (pesan assistant awal dibuang)
        $this->assertEquals('user', $sanitized[0]['role']);
        $this->assertStringContainsString('Toko mana yang buka?', $sanitized[0]['content']);

        // 2. Pesan berturut-turut digabungkan
        $this->assertStringContainsString('Yang ratingnya bagus ya.', $sanitized[0]['content']);

        // 3. Pesan bergantian ketat
        $this->assertEquals('assistant', $sanitized[1]['role']);
        $this->assertEquals('user', $sanitized[2]['role']);
        $this->assertEquals('Terima kasih infonya', $sanitized[2]['content']);
    }

    /**
     * (h) Pada production environment, chatbot DILARANG aktif secara diam-diam jika API key belum terpasang.
     */
    public function test_production_environment_without_api_key_refuses_silent_demo(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
        config(['anthropic.api_key' => null]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->withHeader('X-Active-Role', 'customer')
            ->postJson('/api/chatbot/message', [
                'message' => 'Halo cek status cucian',
            ]);

        $response->assertStatus(500);
        $this->assertStringContainsString('Maaf', $response->json('reply'));
        $this->assertFalse($response->json('is_demo'));
    }

    /**
     * (i) Role Kurir dapat menanyakan panduan tugas dan prosedur pembayaran COD.
     */
    public function test_kurir_can_ask_guidance_and_cod_procedure(): void
    {
        $kurir = User::factory()->create(['name' => 'Doni Kurir']);
        $kurir->assignRole(UserRole::KURIR->value);

        $response = $this->actingAs($kurir, 'sanctum')
            ->withHeader('X-Active-Role', 'kurir')
            ->postJson('/api/chatbot/message', [
                'message' => 'Bagaimana sistem pembayaran COD dan tugas penjemputan?',
            ]);

        $response->assertStatus(200);
        $reply = $response->json('reply');
        $this->assertStringContainsString('Penjemputan', $reply);
        $this->assertStringContainsString('COD', $reply);
    }
}
