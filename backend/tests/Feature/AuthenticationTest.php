<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Mail\ResetPasswordMailable;
use App\Mail\StaffInvitationMailable;
use App\Mail\VerifyEmailMailable;
use App\Models\AuditLog;
use App\Models\StaffInvitation;
use App\Models\StaffToko;
use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Mail::fake();
    }

    /**
     * (a) Registrasi membuat customer dan email verifikasi terkirim via queue.
     */
    public function test_customer_registration_creates_account_and_queues_verification_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Fajar Pratama',
            'email' => 'fajar@example.com',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'fajar@example.com',
            'status' => 'pending_email',
            'email_verified_at' => null,
        ]);

        $user = User::where('email', 'fajar@example.com')->first();
        $this->assertTrue($user->hasRole(UserRole::CUSTOMER->value));
        $this->assertFalse($user->hasVerifiedEmail());

        // Verifikasi email terkirim ke queue
        Mail::assertQueued(VerifyEmailMailable::class, function ($mail) use ($user) {
            return $mail->user->id === $user->id;
        });

        // Catat ke audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_REGISTERED',
            'target_id' => $user->id,
        ]);
    }

    /**
     * (b) Customer yang belum terverifikasi TIDAK BISA membuat order atau mendaftarkan toko.
     */
    public function test_unverified_customer_cannot_create_order_or_register_toko(): void
    {
        // Setup toko & tarif
        $owner = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
        $owner->assignRole(UserRole::OWNER_TOKO->value);
        $toko = Toko::create(['owner_id' => $owner->id, 'nama' => 'Laundry A', 'alamat' => 'Alamat A', 'status' => 'approved']);
        $tarif = Tarif::create(['toko_id' => $toko->id, 'jenis_layanan' => 'Kiloan', 'harga' => 8000]);

        // Customer belum verifikasi email
        $unverifiedCustomer = User::factory()->create([
            'email_verified_at' => null,
            'status' => 'active',
        ]);
        $unverifiedCustomer->assignRole(UserRole::CUSTOMER->value);

        // 1. Coba buat order -> Ditolak (403)
        $orderRes = $this->actingAs($unverifiedCustomer, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::CUSTOMER->value)
            ->postJson('/api/customer/orders', [
                'toko_id' => $toko->id,
                'tarif_id' => $tarif->id,
                'alamat_jemput' => 'Jl. Mawar No 1',
            ]);

        $orderRes->assertStatus(403);
        $orderRes->assertJsonFragment(['requires_email_verification' => true]);

        // 2. Coba daftarkan toko -> Ditolak (403)
        $tokoRes = $this->actingAs($unverifiedCustomer, 'sanctum')
            ->postJson('/api/owner/tokos', [
                'nama' => 'Toko Baru',
                'alamat' => 'Jl. Melati',
            ]);

        $tokoRes->assertStatus(403);
        $tokoRes->assertJsonFragment(['requires_email_verification' => true]);
    }

    /**
     * (c) Verifikasi email via Signed URL berhasil memvalidasi akun.
     */
    public function test_email_verification_via_signed_url_marks_email_verified(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->get($signedUrl);
        $response->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /**
     * (d) Login gagal memberi pesan generik dan terkena rate limiting.
     */
    public function test_login_failure_gives_generic_error_and_hits_rate_limit(): void
    {
        $user = User::factory()->create([
            'email' => 'user.test@example.com',
            'password' => Hash::make('PasswordBenar123'),
            'status' => 'active',
        ]);

        // Percobaan 1-5 dengan password salah -> 401 pesan generik
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson('/api/auth/login', [
                'email' => 'user.test@example.com',
                'password' => 'PasswordSalah',
            ]);
            $res->assertStatus(401);
            $res->assertJsonFragment(['message' => 'Email atau password yang Anda masukkan salah.']);
        }

        // Percobaan ke-6 -> Kena Rate Limit (429)
        $resLockout = $this->postJson('/api/auth/login', [
            'email' => 'user.test@example.com',
            'password' => 'PasswordSalah',
        ]);
        $resLockout->assertStatus(429);
    }

    /**
     * (e) Akun berstatus disabled dilarang login.
     */
    public function test_disabled_account_cannot_login(): void
    {
        $disabledUser = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('Password123'),
            'status' => 'disabled',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'disabled@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('dinonaktifkan', $response->json('message'));
    }

    /**
     * (f) Platform Owner dan Admin Owner WAJIB 2FA setelah login password.
     */
    public function test_platform_and_admin_owners_must_pass_2fa_after_password_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.owner@example.com',
            'password' => Hash::make('PasswordAdmin123'),
            'status' => 'active',
        ]);
        $admin->assignRole(UserRole::ADMIN_OWNER->value);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin.owner@example.com',
            'password' => 'PasswordAdmin123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('requires_2fa'));

        // Coba akses rute tertutup require2fa dengan token 2fa:pending -> 403
        $token = $response->json('token');
        $protectedRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $protectedRes->assertStatus(403);
        $this->assertTrue($protectedRes->json('requires_2fa'));
    }

    /**
     * (g) Setup 2FA dan verifikasi menggunakan Recovery Code (sekali pakai).
     */
    public function test_2fa_setup_and_recovery_code_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'owner.2fa@example.com',
            'status' => 'active',
        ]);
        $user->assignRole(UserRole::PLATFORM_OWNER->value);
        $pendingToken = $user->createToken('auth_token', ['2fa:pending'])->plainTextToken;

        // 1. Setup 2FA
        $setupRes = $this->withHeader('Authorization', 'Bearer ' . $pendingToken)
            ->getJson('/api/auth/2fa/setup');

        $setupRes->assertStatus(200);
        $recoveryCodes = $setupRes->json('recovery_codes');
        $this->assertCount(8, $recoveryCodes);

        $codeToUse = $recoveryCodes[0];

        // 2. Verifikasi menggunakan salah satu Recovery Code
        $verifyRes = $this->withHeader('Authorization', 'Bearer ' . $pendingToken)
            ->postJson('/api/auth/2fa/verify', [
                'recovery_code' => $codeToUse,
            ]);

        $verifyRes->assertStatus(200);
        $fullToken = $verifyRes->json('token');

        // Pastikan recovery code tersebut sudah terhapus dari daftar (sisa 7)
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);
        $this->assertNotContains($codeToUse, $user->fresh()->two_factor_recovery_codes);

        // Akses rute penuh berhasil dengan token baru
        $this->app['auth']->forgetGuards();
        $meRes = $this->withHeader('Authorization', 'Bearer ' . $fullToken)
            ->getJson('/api/auth/me');
        $meRes->assertStatus(200);
    }

    /**
     * (g2) Alur lengkap lifecycle 2FA & pencegahan redirect loop:
     * - Akun tanpa secret terkonfirmasi diarahkan ke /2fa/setup
     * - Endpoint /auth/2fa/status aman diakses token tahap-tengah
     * - Endpoint non-2FA menolak token tahap-tengah dengan error_code spesifik
     * - Setup & aktivasi OTP mengonfirmasi 2FA
     * - Login berikutnya diarahkan ke /2fa/challenge
     */
    public function test_2fa_intermediate_token_status_endpoint_and_lifecycle(): void
    {
        $platformOwner = User::factory()->create([
            'email' => 'super.admin@example.com',
            'password' => Hash::make('PasswordSuper123'),
            'status' => 'active',
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $platformOwner->assignRole(UserRole::PLATFORM_OWNER->value);

        // 1. Login pertama: password OK, belum punya 2FA terkonfirmasi -> diarahkan ke setup
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'super.admin@example.com',
            'password' => 'PasswordSuper123',
        ]);
        $loginRes->assertStatus(200);
        $this->assertTrue($loginRes->json('requires_2fa'));
        $this->assertEquals('setup', $loginRes->json('two_factor_step'));
        $this->assertEquals('/2fa/setup', $loginRes->json('two_factor_destination'));

        $pendingToken = $loginRes->json('token');

        // 2. Token tahap-tengah mengakses /api/auth/2fa/status -> Berhasil (200)
        $statusRes = $this->withHeader('Authorization', 'Bearer ' . $pendingToken)
            ->getJson('/api/auth/2fa/status');
        $statusRes->assertStatus(200);
        $this->assertTrue($statusRes->json('requires_2fa'));
        $this->assertFalse($statusRes->json('is_confirmed'));
        $this->assertEquals('/2fa/setup', $statusRes->json('destination'));

        // 3. Token tahap-tengah mengakses /api/auth/me (non-2FA) -> Ditolak (403) dengan error_code 2fa_setup_required
        $forbiddenRes = $this->withHeader('Authorization', 'Bearer ' . $pendingToken)
            ->getJson('/api/auth/me');
        $forbiddenRes->assertStatus(403);
        $this->assertEquals('2fa_setup_required', $forbiddenRes->json('error_code'));
        $this->assertEquals('/2fa/setup', $forbiddenRes->json('destination'));

        // 4. Setup 2FA via /api/auth/2fa/setup
        $setupRes = $this->withHeader('Authorization', 'Bearer ' . $pendingToken)
            ->getJson('/api/auth/2fa/setup');
        $setupRes->assertStatus(200);
        $secret = $setupRes->json('secret');
        $this->assertNotEmpty($secret);

        // 5. Verifikasi OTP pertama
        $google2fa = new Google2FA();
        $validOtp = $google2fa->getCurrentOtp($secret);

        $verifyRes = $this->withHeader('Authorization', 'Bearer ' . $pendingToken)
            ->postJson('/api/auth/2fa/verify', ['otp' => $validOtp]);
        $verifyRes->assertStatus(200);
        $this->assertNotNull($platformOwner->fresh()->two_factor_confirmed_at);

        $fullToken = $verifyRes->json('token');

        // 6. Token penuh dapat mengakses rute terlindungi /api/auth/me
        $this->app['auth']->forgetGuards();
        $meRes = $this->withHeader('Authorization', 'Bearer ' . $fullToken)
            ->getJson('/api/auth/me');
        $meRes->assertStatus(200);

        // 7. Login berikutnya (saat sudah terkonfirmasi): diarahkan ke challenge
        $loginAgainRes = $this->postJson('/api/auth/login', [
            'email' => 'super.admin@example.com',
            'password' => 'PasswordSuper123',
        ]);
        $loginAgainRes->assertStatus(200);
        $this->assertEquals('challenge', $loginAgainRes->json('two_factor_step'));
        $this->assertEquals('/2fa/challenge', $loginAgainRes->json('two_factor_destination'));

        $challengeToken = $loginAgainRes->json('token');

        // 8. Cek status 2FA saat sudah terkonfirmasi
        $challengeStatus = $this->withHeader('Authorization', 'Bearer ' . $challengeToken)
            ->getJson('/api/auth/2fa/status');
        $challengeStatus->assertStatus(200);
        $this->assertTrue($challengeStatus->json('is_confirmed'));
        $this->assertEquals('/2fa/challenge', $challengeStatus->json('destination'));
    }

    /**
     * (h) Reset password mencabut semua token lama dan link tidak bisa dipakai dua kali.
     */
    public function test_reset_password_revokes_old_tokens_and_token_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'email' => 'reset.user@example.com',
            'password' => Hash::make('PasswordLama123'),
            'status' => 'active',
        ]);
        $oldToken = $user->createToken('old_token')->plainTextToken;

        // 1. Minta reset password
        $reqRes = $this->postJson('/api/auth/forgot-password', [
            'email' => 'reset.user@example.com',
        ]);
        $reqRes->assertStatus(200);

        // Ambil token dari database
        $record = DB::table('password_reset_tokens')->where('email', 'reset.user@example.com')->first();
        $this->assertNotNull($record);

        // Cari plain token yang cocok
        $plainToken = 'simulated_plain_token';
        DB::table('password_reset_tokens')->where('email', 'reset.user@example.com')->update([
            'token' => hash('sha256', $plainToken),
        ]);

        // 2. Eksekusi reset password
        $resetRes = $this->postJson('/api/auth/reset-password', [
            'email' => 'reset.user@example.com',
            'token' => $plainToken,
            'password' => 'PasswordBaru456',
            'password_confirmation' => 'PasswordBaru456',
        ]);
        $resetRes->assertStatus(200);

        // Password di DB sudah berubah
        $this->assertTrue(Hash::check('PasswordBaru456', $user->fresh()->password));

        // Token Sanctum lama HARUS telah dicabut
        $this->assertCount(0, $user->fresh()->tokens);

        // 3. Coba pakai token reset yang sama lagi -> Ditolak (400)
        $reuseRes = $this->postJson('/api/auth/reset-password', [
            'email' => 'reset.user@example.com',
            'token' => $plainToken,
            'password' => 'PasswordBaruLain789',
            'password_confirmation' => 'PasswordBaruLain789',
        ]);
        $reuseRes->assertStatus(400);
    }

    /**
     * (i) Rute dev-login sudah dihapus total dan selalu 404 di semua environment.
     */
    public function test_dev_login_endpoint_is_completely_removed(): void
    {
        $response = $this->postJson('/api/auth/dev-login', [
            'role' => 'customer',
        ]);

        $response->assertStatus(404);
    }

    /**
     * (j) Artisan Command owner:bootstrap membuat platform_owner pertama dan mewajibkan 2FA.
     */
    public function test_artisan_owner_bootstrap_creates_platform_owner_with_required_2fa(): void
    {
        $this->artisan('owner:bootstrap', [
            'email' => 'super.owner@pusatlaundry.id',
            '--name' => 'Super Owner Baru',
        ])
            ->expectsQuestion('Masukkan password Platform Owner (minimal 8 karakter):', 'RahasiaSuper123')
            ->expectsQuestion('Konfirmasi ulang password:', 'RahasiaSuper123')
            ->assertSuccessful();

        $owner = User::where('email', 'super.owner@pusatlaundry.id')->first();
        $this->assertNotNull($owner);
        $this->assertTrue($owner->hasRole(UserRole::PLATFORM_OWNER->value));
        $this->assertTrue($owner->hasVerifiedEmail());
        $this->assertNull($owner->two_factor_confirmed_at); // Wajib 2FA

        // Catat ke audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'BOOTSTRAP_PLATFORM_OWNER',
            'target_id' => $owner->id,
        ]);
    }

    /**
     * (k) Sistem Undangan Staf & Pencegahan Eskalasi Hak Akses (Privilege Escalation).
     */
    public function test_staff_invitation_flow_and_privilege_escalation_prevention(): void
    {
        // 1. Admin Owner mencoba mengundang Platform Owner -> Ditolak (403)
        $adminOwner = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $adminOwner->assignRole(UserRole::ADMIN_OWNER->value);

        $illegalRes = $this->actingAs($adminOwner, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::ADMIN_OWNER->value)
            ->postJson('/api/invitations', [
                'email' => 'hacker@example.com',
                'role' => UserRole::PLATFORM_OWNER->value,
            ]);
        $illegalRes->assertStatus(403);

        // 2. Owner Toko A mencoba mengundang untuk Toko B -> Ditolak (403)
        $ownerA = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $ownerA->assignRole(UserRole::OWNER_TOKO->value);
        $tokoA = Toko::create(['owner_id' => $ownerA->id, 'nama' => 'Toko A', 'alamat' => 'A', 'status' => 'approved']);

        $ownerB = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $ownerB->assignRole(UserRole::OWNER_TOKO->value);
        $tokoB = Toko::create(['owner_id' => $ownerB->id, 'nama' => 'Toko B', 'alamat' => 'B', 'status' => 'approved']);

        $illegalTokoRes = $this->actingAs($ownerA, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->postJson('/api/invitations', [
                'email' => 'staff@example.com',
                'role' => UserRole::ADMIN_TOKO->value,
                'toko_id' => $tokoB->id, // Bukan tokonya!
            ]);
        $illegalTokoRes->assertStatus(403);

        // 3. Owner Toko A mengundang Admin Toko untuk Toko A miliknya -> Sukses (201)
        $inviteRes = $this->actingAs($ownerA, 'sanctum')
            ->withHeader('X-Active-Role', UserRole::OWNER_TOKO->value)
            ->postJson('/api/invitations', [
                'email' => 'staff.valid@example.com',
                'role' => UserRole::ADMIN_TOKO->value,
                'toko_id' => $tokoA->id,
            ]);
        $inviteRes->assertStatus(201);

        $invitation = StaffInvitation::where('email', 'staff.valid@example.com')->first();
        $this->assertNotNull($invitation);

        // Undangan terkirim via queue
        Mail::assertQueued(StaffInvitationMailable::class);

        // Simulasikan token plain
        $plainToken = 'simulated_invite_token_64chars_abcdef1234567890';
        $invitation->update(['token_hash' => hash('sha256', $plainToken)]);

        // 4. Staf menerima undangan dan membuat password sendiri
        $acceptRes = $this->postJson('/api/invitations/accept', [
            'token' => $plainToken,
            'name' => 'Staf Baru Toko A',
            'password' => 'StafPassword123',
            'password_confirmation' => 'StafPassword123',
        ]);

        $acceptRes->assertStatus(200);

        // Akun staf langsung terbentuk, email terverifikasi, dan terhubung ke toko A
        $staffUser = User::where('email', 'staff.valid@example.com')->first();
        $this->assertNotNull($staffUser);
        $this->assertTrue($staffUser->hasRole(UserRole::ADMIN_TOKO->value));
        $this->assertTrue($staffUser->hasVerifiedEmail());
        $this->assertTrue($tokoA->staff()->where('user_id', $staffUser->id)->exists());

        // Undangan ditandai used
        $this->assertNotNull($invitation->fresh()->used_at);

        // 5. Coba gunakan undangan yang sama lagi -> Ditolak (422)
        $reacceptRes = $this->postJson('/api/invitations/accept', [
            'token' => $plainToken,
            'name' => 'Staf Duplicate',
            'password' => 'StafPassword123',
            'password_confirmation' => 'StafPassword123',
        ]);
        $reacceptRes->assertStatus(422);
    }
}
