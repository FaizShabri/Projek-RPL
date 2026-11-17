<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMailable;
use App\Mail\VerifyEmailMailable;
use App\Models\AuditLog;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\PusatLaundryNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Daftar domain email sementara/disposable yang ditolak.
     */
    protected const DISPOSABLE_EMAIL_DOMAINS = [
        'mailinator.com',
        'tempmail.com',
        '10minutemail.com',
        'guerrillamail.com',
        'trashmail.com',
        'yopmail.com',
        'sharklasers.com',
        'dispostable.com',
        'getairmail.com',
        'mytemp.email',
    ];

    /**
     * Registrasi Akun Customer Baru.
     * POST /api/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $ip = $request->ip();

        // 1. Anti-spam Registrasi: Rate limit per IP (maksimal 5 registrasi per 10 menit per IP)
        $ipRateKey = 'register_ip:' . $ip;
        if (RateLimiter::tooManyAttempts($ipRateKey, 5)) {
            $seconds = RateLimiter::availableIn($ipRateKey);
            return response()->json([
                'message' => "Terlalu banyak permintaan registrasi dari perangkat Anda. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        // 2. Anti-spam: Batasi jumlah akun berstatus 'pending_email' atau 'pending_approval' dari IP yang sama (maksimal 3)
        // Kita simpan jejak pendaftaran di cache untuk IP ini
        $pendingQuotaKey = 'pending_registrations_ip:' . $ip;
        $currentPendingCount = (int) cache()->get($pendingQuotaKey, 0);
        if ($currentPendingCount >= 3) {
            return response()->json([
                'message' => 'Terdapat terlalu banyak akun pendaftaran yang belum diselesaikan dari alamat IP Anda. Harap selesaikan verifikasi akun yang ada terlebih dahulu.',
            ], 429);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal harus 8 karakter.',
        ]);

        $email = strtolower(trim($validated['email']));

        // 3. Anti-spam: Tolak domain disposable email
        $domain = substr(strrchr($email, '@'), 1);
        if (in_array($domain, self::DISPOSABLE_EMAIL_DOMAINS, true)) {
            return response()->json([
                'message' => 'Penggunaan alamat email sekali pakai (disposable email) tidak diizinkan.',
                'errors' => ['email' => ['Alamat email sekali pakai tidak diizinkan. Harap gunakan email resmi/pribadi.']],
            ], 422);
        }

        // 4. Periksa apakah email sudah ada di database
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            // Jika akun sebelumnya ditolak ('rejected'), izinkan daftar ulang dengan batas wajar
            if ($existingUser->status === 'rejected') {
                $retryRateKey = 'register_retry_rejected:' . $existingUser->id;
                if (RateLimiter::tooManyAttempts($retryRateKey, 3)) {
                    $seconds = RateLimiter::availableIn($retryRateKey);
                    return response()->json([
                        'message' => "Batas percobaan pendaftaran ulang tercapai. Silakan coba lagi dalam {$seconds} detik.",
                    ], 429);
                }
                RateLimiter::hit($retryRateKey, 3600); // 3x per jam

                // Reset akun yang ditolak menjadi pendaftaran baru
                $existingUser->update([
                    'name' => $validated['name'],
                    'password' => Hash::make($validated['password']),
                    'status' => 'pending_email',
                    'rejection_reason' => null,
                    'email_verified_at' => null,
                ]);

                $user = $existingUser;
            } else {
                return response()->json([
                    'message' => 'Alamat email ini sudah terdaftar.',
                    'errors' => ['email' => ['Alamat email ini sudah terdaftar.']],
                ], 422);
            }
        } else {
            // Status awal registrasi: 'pending_email'
            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => Hash::make($validated['password']),
                'status' => 'pending_email',
                'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($validated['name']),
            ]);

            $user->assignRole(UserRole::CUSTOMER->value);

            UserProfile::create([
                'user_id' => $user->id,
                'nama_lengkap' => $user->name,
                'alamat_lengkap' => '-',
                'rt_rw' => '000/000',
                'nomor_telepon' => '-',
            ]);
        }

        // Hit IP rate limiter dan tambahkan hitung pending quota
        RateLimiter::hit($ipRateKey, 600);
        cache()->put($pendingQuotaKey, $currentPendingCount + 1, now()->addDay());

        // Buat signed URL verifikasi email berbatas waktu 60 menit
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        try {
            Mail::to($user)->queue(new VerifyEmailMailable($user, $verificationUrl));
        } catch (\Throwable $e) {
            // Log error tanpa menggagalkan proses
        }

        AuditLog::record(
            actorId: $user->id,
            action: 'USER_REGISTERED',
            targetType: User::class,
            targetId: $user->id,
            beforeState: null,
            afterState: [
                'email' => $user->email,
                'status' => $user->status,
                'role' => UserRole::CUSTOMER->value,
            ]
        );

        return response()->json([
            'message' => 'Registrasi berhasil! Tautan verifikasi telah dikirim ke email Anda. Silakan verifikasi email Anda untuk melanjutkan.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ],
            'status' => $user->status,
        ], 201);
    }

    /**
     * Verifikasi Email Bertanda Tangan (Signed URL).
     * GET /api/auth/email/verify/{id}/{hash}
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Tautan verifikasi tidak valid.'], 403);
        }

        if (!$request->hasValidSignature()) {
            return response()->json(['message' => 'Tautan verifikasi telah kedaluwarsa atau tidak sah.'], 403);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            // Cek Pengaturan Bisnis: "Persetujuan akun baru: wajib / otomatis"
            // Default adalah 'wajib' (manual approval)
            $approvalSetting = BusinessSetting::getValue('account_approval_mode', 'wajib');

            if ($approvalSetting === 'otomatis') {
                $newStatus = 'active';
            } else {
                $newStatus = 'pending_approval';
            }

            $oldStatus = $user->status;
            $user->status = $newStatus;
            $user->save();

            AuditLog::record(
                actorId: $user->id,
                action: 'EMAIL_VERIFIED',
                targetType: User::class,
                targetId: $user->id,
                beforeState: ['email_verified_at' => null, 'status' => $oldStatus],
                afterState: ['email_verified_at' => $user->email_verified_at, 'status' => $newStatus]
            );

            // Jika status menjadi pending_approval, kirim notifikasi ke Platform Owner
            if ($newStatus === 'pending_approval') {
                $platformOwners = User::role(UserRole::PLATFORM_OWNER->value)->where('status', 'active')->get();
                foreach ($platformOwners as $po) {
                    try {
                        $po->notify(new PusatLaundryNotification(
                            notificationType: 'new_account_pending_approval',
                            title: 'Pendaftaran Akun Baru Menunggu Persetujuan',
                            message: "Pengguna {$user->name} ({$user->email}) telah memverifikasi email dan menunggu persetujuan.",
                            actionUrl: '/platform-owner#users',
                            metadata: ['user_id' => $user->id, 'email' => $user->email]
                        ));
                    } catch (\Throwable $e) {}
                }
            }
        }

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Alamat email berhasil diverifikasi.',
                'status' => $user->status,
            ], 200);
        }

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $redirectParam = $user->status === 'active' ? 'verified=1' : 'pending_approval=1';
        return redirect()->to($frontendUrl . '/login?' . $redirectParam);
    }

    /**
     * Kirim Ulang Tautan Verifikasi Email.
     * POST /api/auth/email/resend
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $user = $email ? User::where('email', $email)->first() : $request->user();

        if (!$user) {
            return response()->json(['message' => 'Pengguna tidak ditemukan.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Alamat email Anda sudah terverifikasi sebelumnya.'], 200);
        }

        $rateKey = 'resend_email_verification:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'message' => "Terlalu sering meminta tautan verifikasi. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        RateLimiter::hit($rateKey, 300);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        try {
            Mail::to($user)->queue(new VerifyEmailMailable($user, $verificationUrl));
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Tautan verifikasi baru berhasil dikirim ke alamat email Anda.',
        ]);
    }

    /**
     * Login Akun (Email + Password).
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($validated['email']));
        $ip = $request->ip();
        $rateKey = 'login_attempt:' . sha1($email . '|' . $ip);

        // Rate Limiting: 5 percobaan gagal per menit
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'message' => "Terlalu banyak percobaan login yang gagal. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $user = User::where('email', $email)->first();

        // Validasi kredensial (pesan error selalu generik untuk mencegah user enumeration)
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($rateKey, 60);
            return response()->json([
                'message' => 'Email atau password yang Anda masukkan salah.',
            ], 401);
        }

        // B.2: Login dengan password BENAR tetapi akun belum active menampilkan pesan sesuai status
        if ($user->status === 'pending_email') {
            return response()->json([
                'message' => 'Email belum diverifikasi. Silakan periksa inbox email Anda untuk memverifikasi akun.',
                'status' => 'pending_email',
            ], 403);
        }

        if ($user->status === 'pending_approval') {
            return response()->json([
                'message' => 'Akunmu sedang menunggu persetujuan admin.',
                'status' => 'pending_approval',
            ], 403);
        }

        if ($user->status === 'rejected') {
            $reasonText = $user->rejection_reason ? ": {$user->rejection_reason}" : '';
            return response()->json([
                'message' => "Pendaftaran ditolak{$reasonText}.",
                'status' => 'rejected',
                'rejection_reason' => $user->rejection_reason,
            ], 403);
        }

        if ($user->status === 'disabled') {
            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan oleh administrator. Silakan hubungi dukungan platform.',
                'status' => 'disabled',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Akun Anda belum aktif dan tidak dapat mengakses sistem.',
                'status' => $user->status,
            ], 403);
        }

        // Bersihkan rate limit saat login sukses
        RateLimiter::clear($rateKey);

        // Catat waktu login terakhir
        $user->update(['last_login_at' => now()]);

        // Cek apakah user wajib 2FA (platform_owner dan admin_owner)
        $requires2FA = $user->hasAnyRole([UserRole::PLATFORM_OWNER->value, UserRole::ADMIN_OWNER->value]);
        $abilities = $requires2FA ? ['2fa:pending'] : ['*'];

        // Terbitkan token Sanctum
        $token = $user->createToken('auth_token', $abilities)->plainTextToken;

        // Catat ke Audit Log
        AuditLog::record(
            actorId: $user->id,
            action: 'USER_LOGIN',
            targetType: User::class,
            targetId: $user->id,
            beforeState: null,
            afterState: [
                'email' => $user->email,
                'ip' => $ip,
                'requires_2fa' => $requires2FA,
            ]
        );

        $primaryRole = $user->getRoleNames()->first() ?? UserRole::CUSTOMER->value;
        $is2FAConfirmed = !empty($user->two_factor_confirmed_at);

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => $user->load('profile', 'roles'),
            'active_role' => $primaryRole,
            'roles' => $user->getRoleNames(),
            'requires_2fa' => $requires2FA,
            'two_factor_step' => $requires2FA ? ($is2FAConfirmed ? 'challenge' : 'setup') : null,
            'two_factor_destination' => $requires2FA ? ($is2FAConfirmed ? '/2fa/challenge' : '/2fa/setup') : null,
            'email_verified' => $user->hasVerifiedEmail(),
            'status' => $user->status,
        ]);
    }

    /**
     * Logout Sesi Saat Ini.
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Berhasil logout.',
        ]);
    }

    /**
     * Minta Tautan Pengaturan Ulang Password.
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->email));

        $rateKey = 'forgot_password:' . sha1($email . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'message' => "Terlalu banyak permintaan reset password. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }
        RateLimiter::hit($rateKey, 300);

        $user = User::where('email', $email)->first();

        if ($user) {
            $plainToken = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $plainToken);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => $hashedToken,
                    'created_at' => now(),
                ]
            );

            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            $resetUrl = "{$frontendUrl}/reset-password?token={$plainToken}&email=" . urlencode($email);

            try {
                Mail::to($user)->queue(new ResetPasswordMailable($user, $resetUrl));
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'message' => 'Jika email Anda terdaftar dalam sistem kami, tautan pengaturan ulang password telah dikirimkan.',
        ]);
    }

    /**
     * Setel Ulang Password dengan Token Valid.
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal harus 8 karakter.',
        ]);

        $email = strtolower(trim($validated['email']));
        $token = $validated['token'];

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !hash_equals($record->token, hash('sha256', $token))) {
            return response()->json(['message' => 'Tautan pengaturan ulang password tidak valid atau telah kedaluwarsa.'], 400);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json(['message' => 'Tautan pengaturan ulang password telah kedaluwarsa. Silakan minta tautan baru.'], 400);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'Pengguna tidak ditemukan.'], 404);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Cabut seluruh token Sanctum aktif sebelumnya
        $user->tokens()->delete();

        AuditLog::record(
            actorId: $user->id,
            action: 'PASSWORD_RESET',
            targetType: User::class,
            targetId: $user->id,
            beforeState: null,
            afterState: ['email' => $user->email]
        );

        return response()->json([
            'message' => 'Password Anda berhasil diatur ulang. Silakan login kembali dengan password baru Anda.',
        ]);
    }

    /**
     * Ubah Password dari Dalam Akun (Wajib Masukkan Password Lama).
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ], [
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password baru minimal harus 8 karakter.',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Password saat ini yang Anda masukkan salah.',
                'errors' => ['current_password' => ['Password saat ini salah.']],
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        AuditLog::record(
            actorId: $user->id,
            action: 'PASSWORD_CHANGED',
            targetType: User::class,
            targetId: $user->id,
            beforeState: null,
            afterState: ['email' => $user->email]
        );

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }
}
