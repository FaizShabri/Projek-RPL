<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\StaffInvitationMailable;
use App\Models\AuditLog;
use App\Models\StaffInvitation;
use App\Models\StaffToko;
use App\Models\Toko;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;

class StaffInvitationController extends Controller
{
    /**
     * Kirim Undangan Staf Baru (Admin Owner, Admin Toko, Kurir).
     * POST /api/invitations
     */
    public function invite(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeRole = $request->header('X-Active-Role') ?: ($user->getRoleNames()->first() ?? 'customer');

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|string|in:platform_owner,admin_owner,admin_toko,kurir',
            'toko_id' => 'nullable|exists:tokos,id',
        ]);

        $email = strtolower(trim($validated['email']));
        $targetRole = $validated['role'];
        $tokoId = $validated['toko_id'] ?? null;

        // Role platform_owner tidak pernah bisa diundang (wajib via artisan command)
        if ($targetRole === UserRole::PLATFORM_OWNER->value) {
            return response()->json([
                'message' => 'Role Platform Owner tidak dapat diundang melalui undangan staf. Gunakan perintah artisan owner:bootstrap.',
            ], 403);
        }

        // Otorisasi Least Privilege & Pencegahan Eskalasi Hak Akses (Privilege Escalation)
        if ($activeRole === UserRole::PLATFORM_OWNER->value) {
            // Platform Owner berhak mengundang admin_owner, admin_toko, kurir
        } elseif ($activeRole === UserRole::ADMIN_OWNER->value) {
            // Admin Owner TIDAK BOLEH mengundang platform_owner atau sesama admin_owner
            if (in_array($targetRole, [UserRole::PLATFORM_OWNER->value, UserRole::ADMIN_OWNER->value])) {
                return response()->json([
                    'message' => 'Admin Owner tidak memiliki wewenang mengundang akun setingkat atau di atasnya.',
                ], 403);
            }
        } elseif ($activeRole === UserRole::OWNER_TOKO->value) {
            // Owner Toko HANYA boleh mengundang admin_toko dan kurir untuk tokonya sendiri
            if (!in_array($targetRole, [UserRole::ADMIN_TOKO->value, UserRole::KURIR->value])) {
                return response()->json([
                    'message' => 'Owner Toko hanya berhak mengundang Admin Toko dan Kurir.',
                ], 403);
            }

            if (!$tokoId) {
                return response()->json(['message' => 'ID Toko wajib disertakan saat mengundang staf toko.'], 422);
            }

            $ownsToko = $user->tokos()->where('id', $tokoId)->exists();
            if (!$ownsToko) {
                return response()->json(['message' => 'Anda bukan pemilik sah dari toko ini.'], 403);
            }
        } else {
            return response()->json(['message' => 'Role Anda tidak memiliki wewenang mengundang staf.'], 403);
        }

        // Rate Limiting pengiriman undangan (10 undangan per 10 menit per user)
        $rateKey = 'staff_invitation:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'message' => "Terlalu banyak mengirim undangan staf. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        RateLimiter::hit($rateKey, 600);

        // Cek jika user sudah terdaftar dan sudah memiliki role tersebut
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && $existingUser->hasRole($targetRole)) {
            return response()->json([
                'message' => "Pengguna dengan email {$email} sudah aktif dan memiliki role {$targetRole}.",
            ], 422);
        }

        // Batalkan undangan lama yang masih pending untuk email dan role ini
        StaffInvitation::where('email', $email)
            ->where('role', $targetRole)
            ->pending()
            ->update(['cancelled_at' => now()]);

        // Buat token acak berkekuatan tinggi (32 bytes = 64 hex characters)
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $invitation = StaffInvitation::create([
            'email' => $email,
            'role' => $targetRole,
            'toko_id' => $tokoId,
            'invited_by' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(3), // Berlaku 3 hari
        ]);

        $toko = $tokoId ? Toko::find($tokoId) : null;
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $inviteUrl = "{$frontendUrl}/invitation/accept?token={$plainToken}";

        try {
            Mail::to($email)->queue(new StaffInvitationMailable(
                $user,
                $targetRole,
                $toko,
                $inviteUrl,
                $invitation->expires_at->translatedFormat('d F Y H:i') . ' WIB'
            ));
        } catch (\Throwable $e) {
            // Log ke sistem
        }

        AuditLog::record(
            actorId: $user->id,
            action: 'SEND_STAFF_INVITATION',
            targetType: StaffInvitation::class,
            targetId: $invitation->id,
            beforeState: null,
            afterState: [
                'email' => $email,
                'role' => $targetRole,
                'toko_id' => $tokoId,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ]
        );

        return response()->json([
            'message' => "Undangan berhasil dikirim ke {$email}.",
            'invitation' => $invitation->load(['toko:id,nama', 'inviter:id,name']),
        ], 201);
    }

    /**
     * Dapatkan Detail Informasi Undangan via Token Publik.
     * GET /api/invitations/details?token=...
     */
    public function getDetails(Request $request): JsonResponse
    {
        $token = (string) $request->query('token');
        if (!$token) {
            return response()->json(['message' => 'Token undangan wajib disertakan.'], 400);
        }

        $tokenHash = hash('sha256', $token);
        $invitation = StaffInvitation::with(['toko:id,nama', 'inviter:id,name'])
            ->where('token_hash', $tokenHash)
            ->first();

        if (!$invitation) {
            return response()->json(['message' => 'Tautan undangan tidak ditemukan atau tidak sah.'], 404);
        }

        if ($invitation->used_at) {
            return response()->json(['message' => 'Undangan ini telah digunakan sebelumnya.'], 410);
        }

        if ($invitation->cancelled_at) {
            return response()->json(['message' => 'Undangan ini telah dibatalkan oleh pihak pengundang.'], 410);
        }

        if ($invitation->expires_at->isPast()) {
            return response()->json(['message' => 'Tautan undangan telah kedaluwarsa. Silakan minta undangan baru.'], 410);
        }

        return response()->json([
            'email' => $invitation->email,
            'role' => $invitation->role,
            'role_label' => ucwords(str_replace('_', ' ', $invitation->role)),
            'toko' => $invitation->toko ? ['id' => $invitation->toko->id, 'nama' => $invitation->toko->nama] : null,
            'inviter_name' => $invitation->inviter?->name ?? 'Administrator',
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Terima Undangan Staf & Buat Password Akun Sendiri.
     * POST /api/invitations/accept
     */
    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
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

        $tokenHash = hash('sha256', $validated['token']);
        $invitation = StaffInvitation::where('token_hash', $tokenHash)->first();

        if (!$invitation || !$invitation->isPending()) {
            return response()->json([
                'message' => 'Undangan tidak valid, telah kedaluwarsa, atau telah digunakan sebelumnya.',
            ], 422);
        }

        // Cari atau buat User baru
        $user = User::firstOrNew(['email' => $invitation->email]);
        $user->name = $validated['name'];
        $user->password = Hash::make($validated['password']);
        $user->email_verified_at = now(); // Email otomatis terverifikasi lewat tautan undangan
        $user->status = 'active';
        $user->avatar = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($validated['name']);
        $user->save();

        // Assign role dari undangan
        if (!$user->hasRole($invitation->role)) {
            $user->assignRole($invitation->role);
        }

        // Jika staf toko (admin_toko), hubungkan ke toko bersangkutan
        if ($invitation->toko_id && in_array($invitation->role, [UserRole::ADMIN_TOKO->value])) {
            StaffToko::firstOrCreate([
                'user_id' => $user->id,
                'toko_id' => $invitation->toko_id,
            ]);
        }

        // Buat profile jika belum ada
        if (!$user->profile) {
            UserProfile::create([
                'user_id' => $user->id,
                'nama_lengkap' => $user->name,
                'alamat_lengkap' => '-',
                'rt_rw' => '000/000',
                'nomor_telepon' => '-',
            ]);
        }

        // Tandai undangan sudah digunakan (used_at)
        $invitation->update(['used_at' => now()]);

        // Cek 2FA jika role admin_owner
        $requires2FA = in_array($invitation->role, [UserRole::PLATFORM_OWNER->value, UserRole::ADMIN_OWNER->value]);
        $abilities = $requires2FA ? ['2fa:pending'] : ['*'];

        $token = $user->createToken('auth_token', $abilities)->plainTextToken;

        AuditLog::record(
            actorId: $user->id,
            action: 'STAFF_INVITATION_ACCEPTED',
            targetType: User::class,
            targetId: $user->id,
            beforeState: null,
            afterState: [
                'email' => $user->email,
                'role' => $invitation->role,
                'toko_id' => $invitation->toko_id,
                'invitation_id' => $invitation->id,
            ]
        );

        return response()->json([
            'message' => "Akun berhasil dibuat! Selamat bergabung sebagai {$invitation->role}.",
            'token' => $token,
            'user' => $user->load('profile', 'roles'),
            'active_role' => $invitation->role,
            'roles' => $user->getRoleNames(),
            'requires_2fa' => $requires2FA,
        ]);
    }

    /**
     * Batalkan Undangan Staf.
     * POST /api/invitations/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $invitation = StaffInvitation::findOrFail($id);

        if (!$invitation->isPending()) {
            return response()->json(['message' => 'Undangan ini sudah tidak dalam status pending.'], 422);
        }

        $invitation->update(['cancelled_at' => now()]);

        AuditLog::record(
            actorId: $user->id,
            action: 'STAFF_INVITATION_CANCELLED',
            targetType: StaffInvitation::class,
            targetId: $invitation->id,
            beforeState: ['status' => 'pending'],
            afterState: ['cancelled_at' => now()->toIso8601String()]
        );

        return response()->json(['message' => 'Undangan berhasil dibatalkan.']);
    }
}
