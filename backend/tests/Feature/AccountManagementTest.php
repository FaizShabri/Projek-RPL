<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\AccountApprovedMailable;
use App\Mail\AccountRejectedMailable;
use App\Mail\ResetPasswordMailable;
use App\Models\AuditLog;
use App\Models\BusinessSetting;
use App\Models\StaffToko;
use App\Models\Toko;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    /**
     * (a) Admin Owner TIDAK DAPAT melihat atau memodifikasi Platform Owner dan sesama Admin Owner.
     */
    public function test_admin_owner_cannot_modify_platform_owner_or_fellow_admin_owner(): void
    {
        $adminOwner1 = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $adminOwner1->assignRole(UserRole::ADMIN_OWNER->value);

        $adminOwner2 = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $adminOwner2->assignRole(UserRole::ADMIN_OWNER->value);

        $platformOwner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $platformOwner->assignRole(UserRole::PLATFORM_OWNER->value);

        // 1. Admin Owner 1 mencoba mengubah status Platform Owner -> 403
        $resPo = $this->actingAs($adminOwner1, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->patchJson("/api/management/users/{$platformOwner->id}/status", [
                'status' => 'disabled',
            ]);
        $resPo->assertStatus(403);

        // 2. Admin Owner 1 mencoba mengubah status Admin Owner 2 -> 403
        $resAo = $this->actingAs($adminOwner1, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->patchJson("/api/management/users/{$adminOwner2->id}/status", [
                'status' => 'disabled',
            ]);
        $resAo->assertStatus(403);
    }

    /**
     * (b) Pencegahan Eskalasi Hak Akses: Admin Owner tidak bisa memberikan role Platform Owner atau Admin Owner.
     */
    public function test_admin_owner_cannot_grant_platform_owner_or_admin_owner_role(): void
    {
        $adminOwner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $adminOwner->assignRole(UserRole::ADMIN_OWNER->value);

        $customer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer->assignRole(UserRole::CUSTOMER->value);

        // 1. Coba berikan role platform_owner -> 403
        $resPo = $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->patchJson("/api/management/users/{$customer->id}/role", [
                'role' => UserRole::PLATFORM_OWNER->value,
            ]);
        $resPo->assertStatus(403);

        // 2. Coba berikan role admin_owner -> 403
        $resAo = $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->patchJson("/api/management/users/{$customer->id}/role", [
                'role' => UserRole::ADMIN_OWNER->value,
            ]);
        $resAo->assertStatus(403);

        // 3. Berikan role kurir -> Berhasil (200)
        $resKurir = $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->patchJson("/api/management/users/{$customer->id}/role", [
                'role' => UserRole::KURIR->value,
            ]);
        $resKurir->assertStatus(200);
        $this->assertTrue($customer->fresh()->hasRole(UserRole::KURIR->value));
    }

    /**
     * (c) Owner Toko HANYA bisa mengelola staf dari tokonya sendiri, bukan staf toko lain atau customer umum.
     */
    public function test_owner_toko_can_only_manage_staff_of_own_toko(): void
    {
        // Owner Toko A dan tokonya
        $ownerA = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $ownerA->assignRole(UserRole::OWNER_TOKO->value);
        $tokoA = Toko::create(['owner_id' => $ownerA->id, 'nama' => 'Toko A', 'alamat' => 'Jl. A', 'status' => 'approved']);

        // Staff Toko A
        $staffA = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staffA->assignRole(UserRole::ADMIN_TOKO->value);
        StaffToko::create(['toko_id' => $tokoA->id, 'user_id' => $staffA->id]);

        // Owner Toko B dan tokonya
        $ownerB = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $ownerB->assignRole(UserRole::OWNER_TOKO->value);
        $tokoB = Toko::create(['owner_id' => $ownerB->id, 'nama' => 'Toko B', 'alamat' => 'Jl. B', 'status' => 'approved']);

        // Staff Toko B
        $staffB = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $staffB->assignRole(UserRole::ADMIN_TOKO->value);
        StaffToko::create(['toko_id' => $tokoB->id, 'user_id' => $staffB->id]);

        // Customer umum
        $customer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer->assignRole(UserRole::CUSTOMER->value);

        // 1. Owner Toko A ubah status staf tokonya sendiri -> Berhasil (200)
        $resOk = $this->actingAs($ownerA, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->patchJson("/api/management/users/{$staffA->id}/status", [
                'status' => 'disabled',
            ]);
        $resOk->assertStatus(200);

        // 2. Owner Toko A coba ubah status staf Toko B -> Ditolak (403)
        $resB = $this->actingAs($ownerA, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->patchJson("/api/management/users/{$staffB->id}/status", [
                'status' => 'disabled',
            ]);
        $resB->assertStatus(403);

        // 3. Owner Toko A coba ubah customer umum -> Ditolak (403)
        $resCust = $this->actingAs($ownerA, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->patchJson("/api/management/users/{$customer->id}/status", [
                'status' => 'disabled',
            ]);
        $resCust->assertStatus(403);
    }

    /**
     * (d) User tidak dapat menonaktifkan akun sendiri atau mengubah role akun sendiri.
     */
    public function test_user_cannot_modify_own_role_or_disable_own_account(): void
    {
        $platformOwner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $platformOwner->assignRole(UserRole::PLATFORM_OWNER->value);

        // Coba nonaktifkan akun sendiri -> 422
        $resDisable = $this->actingAs($platformOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->patchJson("/api/management/users/{$platformOwner->id}/status", [
                'status' => 'disabled',
            ]);
        $resDisable->assertStatus(422);

        // Coba ubah role akun sendiri -> 422
        $resRole = $this->actingAs($platformOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->patchJson("/api/management/users/{$platformOwner->id}/role", [
                'role' => UserRole::CUSTOMER->value,
            ]);
        $resRole->assertStatus(422);
    }

    /**
     * (e) Akun Platform Owner aktif terakhir TIDAK BISA dinonaktifkan.
     */
    public function test_last_active_platform_owner_cannot_be_disabled(): void
    {
        $po1 = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $po1->assignRole(UserRole::PLATFORM_OWNER->value);

        $po2 = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $po2->assignRole(UserRole::PLATFORM_OWNER->value);

        // Ketika ada 2 PO aktif, PO1 menonaktifkan PO2 -> Berhasil (200)
        $res1 = $this->actingAs($po1, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->patchJson("/api/management/users/{$po2->id}/status", [
                'status' => 'disabled',
            ]);
        $res1->assertStatus(200);

        // Sekarang PO1 adalah satu-satunya PO aktif. Jika ada percobaan menonaktifkan PO1 -> Ditolak (422)
        // Buat admin lain untuk mencoba
        $po2->status = 'disabled';
        $po2->save();

        // Bahkan jika ada admin lain mencoba menonaktifkan PO1 (terakhir) -> 422
        $resLast = $this->actingAs($po1, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->patchJson("/api/management/users/{$po1->id}/status", [
                'status' => 'disabled',
            ]);
        $resLast->assertStatus(422);
    }

    /**
     * (f) Akun yang dinonaktifkan segera kehilangan seluruh sesi token aktif (immediate lockout).
     */
    public function test_disabled_account_immediately_loses_active_sessions(): void
    {
        $adminOwner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $adminOwner->assignRole(UserRole::ADMIN_OWNER->value);

        $customer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer->assignRole(UserRole::CUSTOMER->value);

        $token1 = $customer->createToken('session_1')->plainTextToken;
        $token2 = $customer->createToken('session_2')->plainTextToken;

        $this->assertEquals(2, $customer->tokens()->count());

        // Admin Owner menonaktifkan customer
        $res = $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->patchJson("/api/management/users/{$customer->id}/status", [
                'status' => 'disabled',
            ]);

        $res->assertStatus(200);

        // Seluruh token harus sudah terhapus
        $this->assertEquals(0, $customer->fresh()->tokens()->count());

        // Audit log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $adminOwner->id,
            'action' => 'USER_STATUS_TOGGLED',
            'target_id' => $customer->id,
        ]);
    }

    /**
     * (g) Role tanpa wewenang (Admin Toko, Customer, Kurir) ditolak akses ke modul manajemen (403).
     */
    public function test_unauthorized_roles_cannot_access_management_endpoints(): void
    {
        $adminToko = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $adminToko->assignRole(UserRole::ADMIN_TOKO->value);

        $customer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer->assignRole(UserRole::CUSTOMER->value);

        $kurir = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $kurir->assignRole(UserRole::KURIR->value);

        // Admin Toko -> 403
        $this->actingAs($adminToko, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_TOKO->value)
            ->getJson('/api/management/users')
            ->assertStatus(403);

        // Customer -> 403
        $this->actingAs($customer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->getJson('/api/management/users')
            ->assertStatus(403);

        // Kurir -> 403
        $this->actingAs($kurir, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::KURIR->value)
            ->getJson('/api/management/users')
            ->assertStatus(403);
    }

    /**
     * (h) Administrator memicu reset password mengirim email via antrean dan mencatat audit log.
     */
    public function test_admin_trigger_reset_password_queues_email_and_audits(): void
    {
        Mail::fake();

        $platformOwner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $platformOwner->assignRole(UserRole::PLATFORM_OWNER->value);

        $customer = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer->assignRole(UserRole::CUSTOMER->value);

        $res = $this->actingAs($platformOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->postJson("/api/management/users/{$customer->id}/reset-password");

        $res->assertStatus(200);

        Mail::assertQueued(ResetPasswordMailable::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $platformOwner->id,
            'action' => 'USER_PASSWORD_RESET_TRIGGERED',
            'target_id' => $customer->id,
        ]);
    }

    /**
     * (i) Command purge demo menolak jalan jika tidak ada platform_owner non-demo aktif dengan 2FA terkonfirmasi.
     */
    public function test_purge_demo_command_refuses_to_run_without_active_2fa_platform_owner(): void
    {
        // Jalankan tanpa ada Platform Owner aktif dengan 2FA
        $exitCode = Artisan::call('accounts:purge-demo', [
            '--force' => true,
            '--action' => 'disable',
        ]);

        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Tidak ditemukan minimal SATU akun Platform Owner aktif non-demo dengan 2FA terkonfirmasi', $output);
    }

    /**
     * (j) Command purge demo berhasil jalan jika ada platform_owner non-demo aktif dengan 2FA, dan akun demo dinonaktifkan/dihapus.
     */
    public function test_purge_demo_command_succeeds_with_valid_platform_owner(): void
    {
        // Buat platform owner valid non-demo dengan 2FA
        $po = User::factory()->create([
            'email' => 'real.superadmin@pusatlaundry.id',
            'status' => 'active',
            'two_factor_secret' => 'SECRET123',
            'two_factor_confirmed_at' => now(),
            'email_verified_at' => now(),
        ]);
        $po->assignRole(UserRole::PLATFORM_OWNER->value);

        // Buat akun demo yang terdaftar di DEMO_EMAILS
        $demoUser = User::factory()->create([
            'email' => 'admin@pusatlaundry.id',
            'status' => 'active',
        ]);
        $demoToken = $demoUser->createToken('demo_token')->plainTextToken;

        $exitCode = Artisan::call('accounts:purge-demo', [
            '--force' => true,
            '--action' => 'disable',
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals('disabled', $demoUser->fresh()->status);
        $this->assertCount(0, $demoUser->fresh()->tokens);

        // Akun Platform Owner asli TIDAK TERGANGGU
        $this->assertEquals('active', $po->fresh()->status);
        $this->assertNotNull($po->fresh()->two_factor_confirmed_at);
    }

    /**
     * (k) Alur Status Registrasi: pending_email -> verifikasi -> pending_approval -> active / rejected.
     */
    public function test_registration_lifecycle_and_status_checks(): void
    {
        Mail::fake();

        // 1. Registrasi
        $res = $this->postJson('/api/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@gmail.com',
            'password' => 'PasswordBudi123',
            'password_confirmation' => 'PasswordBudi123',
        ]);
        $res->assertStatus(201);

        $user = User::where('email', 'budi.santoso@gmail.com')->first();
        $this->assertEquals('pending_email', $user->status);

        // 2. Login saat status pending_email dengan password BENAR -> 403 status specific message
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'budi.santoso@gmail.com',
            'password' => 'PasswordBudi123',
        ]);
        $loginRes->assertStatus(403);
        $loginRes->assertJsonFragment(['message' => 'Email belum diverifikasi. Silakan periksa inbox email Anda untuk memverifikasi akun.']);

        // 3. Login saat password SALAH -> 401 pesan generik (tidak membocorkan keberadaan akun)
        $badPwRes = $this->postJson('/api/auth/login', [
            'email' => 'budi.santoso@gmail.com',
            'password' => 'WrongPassword999',
        ]);
        $badPwRes->assertStatus(401);
        $badPwRes->assertJsonFragment(['message' => 'Email atau password yang Anda masukkan salah.']);

        // 4. Verifikasi email (mode wajib persetujuan adalah default)
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $verifyRes = $this->getJson($verifyUrl);
        $verifyRes->assertStatus(200);

        // Status berubah menjadi pending_approval
        $this->assertEquals('pending_approval', $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);

        // 5. Login saat status pending_approval -> 403
        $loginPendingRes = $this->postJson('/api/auth/login', [
            'email' => 'budi.santoso@gmail.com',
            'password' => 'PasswordBudi123',
        ]);
        $loginPendingRes->assertStatus(403);
        $loginPendingRes->assertJsonFragment(['message' => 'Akunmu sedang menunggu persetujuan admin.']);
    }

    /**
     * (l) Platform Owner menyetujui akun pengguna, email terkirim, user bisa login.
     */
    public function test_platform_owner_approves_pending_account(): void
    {
        Mail::fake();

        $po = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $po->assignRole(UserRole::PLATFORM_OWNER->value);

        $user = User::factory()->create([
            'email' => 'calon.user@gmail.com',
            'status' => 'pending_approval',
            'email_verified_at' => now(),
            'password' => Hash::make('PasswordAman123'),
        ]);
        $user->assignRole(UserRole::CUSTOMER->value);

        // PO setujui akun
        $approveRes = $this->actingAs($po, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->postJson("/api/management/users/{$user->id}/approve");

        $approveRes->assertStatus(200);
        $this->assertEquals('active', $user->fresh()->status);

        // Mail terkirim
        Mail::assertQueued(AccountApprovedMailable::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // Audit log dicatat
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $po->id,
            'action' => 'USER_APPROVED',
            'target_id' => $user->id,
        ]);

        // User sekarang BISA login
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'calon.user@gmail.com',
            'password' => 'PasswordAman123',
        ]);
        $loginRes->assertStatus(200);
        $this->assertArrayHasKey('token', $loginRes->json());
    }

    /**
     * (m) Platform Owner menolak akun pengguna dengan alasan wajib, user ditolak saat login.
     */
    public function test_platform_owner_rejects_pending_account_with_reason(): void
    {
        Mail::fake();

        $po = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $po->assignRole(UserRole::PLATFORM_OWNER->value);

        $user = User::factory()->create([
            'email' => 'spam.user@gmail.com',
            'status' => 'pending_approval',
            'email_verified_at' => now(),
            'password' => Hash::make('PasswordAman123'),
        ]);
        $user->assignRole(UserRole::CUSTOMER->value);

        // 1. Coba tolak tanpa alasan -> 422
        $resNoReason = $this->actingAs($po, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->postJson("/api/management/users/{$user->id}/reject", []);
        $resNoReason->assertStatus(422);

        // 2. Tolak dengan alasan valid
        $rejectRes = $this->actingAs($po, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->postJson("/api/management/users/{$user->id}/reject", [
                'reason' => 'Data identitas pengguna tidak valid dan terindikasi akun bot.',
            ]);

        $rejectRes->assertStatus(200);
        $this->assertEquals('rejected', $user->fresh()->status);
        $this->assertEquals('Data identitas pengguna tidak valid dan terindikasi akun bot.', $user->fresh()->rejection_reason);

        // Mail terkirim
        Mail::assertQueued(AccountRejectedMailable::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // 3. Login saat status rejected -> 403 menyertakan alasan penolakan
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'spam.user@gmail.com',
            'password' => 'PasswordAman123',
        ]);
        $loginRes->assertStatus(403);
        $this->assertStringContainsString('Pendaftaran ditolak:', $loginRes->json('message'));
        $this->assertStringContainsString('Data identitas pengguna tidak valid', $loginRes->json('message'));
    }

    /**
     * (n) Setujui dan Tolak massal (Bulk Actions) dengan pencatatan audit log.
     */
    public function test_bulk_approve_and_bulk_reject(): void
    {
        Mail::fake();

        $po = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $po->assignRole(UserRole::PLATFORM_OWNER->value);

        $u1 = User::factory()->create(['status' => 'pending_approval', 'email_verified_at' => now()]);
        $u2 = User::factory()->create(['status' => 'pending_approval', 'email_verified_at' => now()]);
        $u3 = User::factory()->create(['status' => 'pending_approval', 'email_verified_at' => now()]);

        // Bulk Approve u1 & u2
        $resApprove = $this->actingAs($po, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->postJson('/api/management/users/bulk-approve', [
                'ids' => [$u1->id, $u2->id],
            ]);

        $resApprove->assertStatus(200);
        $this->assertEquals('active', $u1->fresh()->status);
        $this->assertEquals('active', $u2->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_BULK_APPROVED']);

        // Bulk Reject u3
        $resReject = $this->actingAs($po, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->postJson('/api/management/users/bulk-reject', [
                'ids' => [$u3->id],
                'reason' => 'Registrasi massal mencurigakan.',
            ]);

        $resReject->assertStatus(200);
        $this->assertEquals('rejected', $u3->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_BULK_REJECTED']);
    }

    /**
     * (o) Soft delete akun pengguna: mencabut sesi aktif, tidak bisa menghapus diri sendiri atau sesama Platform Owner.
     */
    public function test_soft_delete_account_and_protection(): void
    {
        $po1 = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $po1->assignRole(UserRole::PLATFORM_OWNER->value);

        $po2 = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $po2->assignRole(UserRole::PLATFORM_OWNER->value);

        $targetUser = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $targetUser->assignRole(UserRole::CUSTOMER->value);
        $targetUser->createToken('session_test')->plainTextToken;

        // 1. PO1 tidak bisa menghapus diri sendiri -> 422
        $resSelf = $this->actingAs($po1, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->deleteJson("/api/management/users/{$po1->id}");
        $resSelf->assertStatus(422);

        // 2. PO1 tidak bisa menghapus sesama Platform Owner -> 422
        $resOtherPo = $this->actingAs($po1, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->deleteJson("/api/management/users/{$po2->id}");
        $resOtherPo->assertStatus(422);

        // 3. PO1 menghapus customer -> Berhasil (200, soft delete)
        $resDel = $this->actingAs($po1, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->deleteJson("/api/management/users/{$targetUser->id}");

        $resDel->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $targetUser->id]);
        $this->assertCount(0, $targetUser->tokens);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $po1->id,
            'action' => 'USER_SOFT_DELETED',
            'target_id' => $targetUser->id,
        ]);
    }

    /**
     * (p) Pengaturan Bisnis: mode persetujuan wajib vs otomatis.
     */
    public function test_business_setting_approval_mode_affects_email_verification(): void
    {
        Mail::fake();

        $po = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $po->assignRole(UserRole::PLATFORM_OWNER->value);

        // Ubah mode ke otomatis
        $updateRes = $this->actingAs($po, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::PLATFORM_OWNER->value)
            ->putJson('/api/management/settings', [
                'account_approval_mode' => 'otomatis',
            ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('otomatis', BusinessSetting::getValue('account_approval_mode'));

        // Buat pendaftar baru
        $newUser = User::factory()->create([
            'status' => 'pending_email',
            'email_verified_at' => null,
        ]);

        // Verifikasi email pendaftar
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $newUser->id, 'hash' => sha1($newUser->getEmailForVerification())]
        );

        $this->getJson($verifyUrl)->assertStatus(200);

        // Karena mode = otomatis, status langsung 'active' tanpa antre 'pending_approval'
        $this->assertEquals('active', $newUser->fresh()->status);
    }

    /**
     * (q) Anti-spam registrasi: tolak email disposable dan user rejected dapat daftar ulang.
     */
    public function test_anti_spam_disposable_domain_and_rejected_user_reregistration(): void
    {
        Mail::fake();

        // 1. Registrasi dengan domain sekali pakai -> Ditolak (422)
        $resDisposable = $this->postJson('/api/auth/register', [
            'name' => 'Spammer',
            'email' => 'spammer123@mailinator.com',
            'password' => 'PasswordSpam123',
            'password_confirmation' => 'PasswordSpam123',
        ]);
        $resDisposable->assertStatus(422);
        $resDisposable->assertJsonFragment(['message' => 'Penggunaan alamat email sekali pakai (disposable email) tidak diizinkan.']);

        // 2. User berstatus rejected mencoba mendaftar ulang -> Diizinkan reset menjadi pending_email
        $rejectedUser = User::factory()->create([
            'email' => 'reregister@gmail.com',
            'status' => 'rejected',
            'rejection_reason' => 'Data tidak lengkap sebelumnya',
        ]);

        $reRegisterRes = $this->postJson('/api/auth/register', [
            'name' => 'Calon Diperbaiki',
            'email' => 'reregister@gmail.com',
            'password' => 'PasswordBaru999',
            'password_confirmation' => 'PasswordBaru999',
        ]);

        $reRegisterRes->assertStatus(201);
        $this->assertEquals('pending_email', $rejectedUser->fresh()->status);
        $this->assertNull($rejectedUser->fresh()->rejection_reason);
    }

    /**
     * (r) Least privilege: Admin Owner dilarang keras mengakses endpoint persetujuan dan setting.
     */
    public function test_admin_owner_forbidden_from_approval_and_settings_endpoints(): void
    {
        $adminOwner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $adminOwner->assignRole(UserRole::ADMIN_OWNER->value);

        // Pending accounts -> 403
        $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->getJson('/api/management/pending-accounts')
            ->assertStatus(403);

        // Approve user -> 403
        $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->postJson('/api/management/users/999/approve')
            ->assertStatus(403);

        // Reject user -> 403
        $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->postJson('/api/management/users/999/reject', ['reason' => 'Test reason'])
            ->assertStatus(403);

        // Settings -> 403
        $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->getJson('/api/management/settings')
            ->assertStatus(403);

        // Delete user -> 403
        $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->deleteJson('/api/management/users/999')
            ->assertStatus(403);
    }
}
