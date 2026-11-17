<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Mengambil status autentikasi 2FA saat ini (aman diakses oleh token 2fa:pending).
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $isConfirmed = !empty($user->two_factor_confirmed_at);
        $hasSecret = !empty($user->two_factor_secret);

        return response()->json([
            'requires_2fa' => true,
            'has_secret' => $hasSecret,
            'is_confirmed' => $isConfirmed,
            'destination' => $isConfirmed ? '/2fa/challenge' : '/2fa/setup',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first() ?? 'customer',
            ],
        ]);
    }

    /**
     * Men-generate secret key, QR code, dan recovery codes untuk setup 2FA.
     */
    public function setup(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        // Generate secret jika belum ada
        if (!$user->two_factor_secret) {
            $user->two_factor_secret = $google2fa->generateSecretKey();
        }

        // Generate 8 recovery codes jika belum ada
        if (empty($user->two_factor_recovery_codes)) {
            $codes = collect(range(1, 8))
                ->map(fn () => strtoupper(bin2hex(random_bytes(5))))
                ->all();
            $user->two_factor_recovery_codes = $codes;
        }

        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Pusat Laundry'),
            $user->email,
            $user->two_factor_secret
        );

        return response()->json([
            'secret' => $user->two_factor_secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $user->two_factor_recovery_codes,
        ]);
    }

    /**
     * Memvalidasi OTP TOTP atau Recovery Code dan memberikan token dengan akses penuh.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $user = $request->user();
        $otp = trim((string) $request->input('otp'));
        $recoveryCode = strtoupper(trim((string) $request->input('recovery_code')));

        if (!$otp && !$recoveryCode) {
            return response()->json([
                'message' => 'Harap masukkan kode OTP atau kode pemulihan (recovery code).',
            ], 422);
        }

        $isValid = false;
        $usedRecovery = false;

        // 1. Cek Recovery Code jika diisi
        if ($recoveryCode && !empty($user->two_factor_recovery_codes)) {
            $codes = (array) $user->two_factor_recovery_codes;
            $index = array_search($recoveryCode, $codes, true);

            if ($index !== false) {
                // Hapus recovery code yang sudah dipakai (one-time use)
                unset($codes[$index]);
                $user->two_factor_recovery_codes = array_values($codes);
                $isValid = true;
                $usedRecovery = true;

                AuditLog::record(
                    actorId: $user->id,
                    action: '2FA_RECOVERY_CODE_USED',
                    targetType: get_class($user),
                    targetId: $user->id,
                    beforeState: null,
                    afterState: ['remaining_codes_count' => count($user->two_factor_recovery_codes)]
                );
            }
        }

        // 2. Cek OTP TOTP jika recovery code tidak dipakai
        if (!$isValid && $otp && $user->two_factor_secret) {
            $google2fa = new Google2FA();
            $isValid = $google2fa->verifyKey($user->two_factor_secret, $otp);
        }

        if ($isValid) {
            // Tandai sudah diverifikasi jika baru pertama kali
            if (!$user->two_factor_confirmed_at) {
                $user->two_factor_confirmed_at = now();
            }
            $user->save();

            // Hapus token yang lama (dengan ability 2fa:pending)
            $request->user()->currentAccessToken()->delete();

            // Buat token baru dengan ability penuh
            $token = $user->createToken('auth_token', ['*'])->plainTextToken;
            $primaryRole = $user->getRoleNames()->first() ?? 'customer';

            return response()->json([
                'message' => $usedRecovery ? '2FA diverifikasi menggunakan Kode Pemulihan.' : '2FA Berhasil Diverifikasi.',
                'token' => $token,
                'user' => $user->load('profile', 'roles'),
                'active_role' => $primaryRole,
                'roles' => $user->getRoleNames(),
                'used_recovery_code' => $usedRecovery,
                'remaining_recovery_codes' => count($user->two_factor_recovery_codes ?? []),
            ]);
        }

        return response()->json([
            'message' => 'Kode OTP atau Kode Pemulihan tidak valid.',
        ], 401);
    }
}
