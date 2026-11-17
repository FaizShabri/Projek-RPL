<?php

namespace App\Http\Controllers\Api\Management;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\AccountApprovedMailable;
use App\Mail\AccountRejectedMailable;
use App\Mail\ResetPasswordMailable;
use App\Models\AuditLog;
use App\Models\BusinessSetting;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\PusatLaundryNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountManagementController extends Controller
{
    /**
     * Helper untuk mendapatkan role aktif pemanggil.
     */
    protected function getActiveRole(Request $request): string
    {
        $user = $request->user();
        return $request->header('X-Active-Role') ?: ($user->getRoleNames()->first() ?? 'customer');
    }

    /**
     * Memeriksa otorisasi apakah pemanggil berhak mengakses menu manajemen.
     */
    protected function authorizeManagementAccess(Request $request): string
    {
        $activeRole = $this->getActiveRole($request);

        if (!in_array($activeRole, [
            UserRole::PLATFORM_OWNER->value,
            UserRole::ADMIN_OWNER->value,
            UserRole::OWNER_TOKO->value,
        ])) {
            abort(403, 'Anda tidak memiliki hak akses ke menu Manajemen Akun.');
        }

        return $activeRole;
    }

    /**
     * Memeriksa apakah pemanggil memiliki izin khusus Platform Owner (Least Privilege lapis 2).
     */
    protected function authorizePlatformOwnerPermission(Request $request, string $permission): User
    {
        $caller = $request->user();
        $callerRole = $this->getActiveRole($request);

        if ($callerRole !== UserRole::PLATFORM_OWNER->value) {
            abort(403, 'Hanya Platform Owner yang memiliki akses ke fitur ini.');
        }

        if (!$caller->hasRole(UserRole::PLATFORM_OWNER->value) || !$caller->can($permission)) {
            abort(403, "Akses ditolak. Diperlukan izin [{$permission}].");
        }

        return $caller;
    }

    /**
     * Memeriksa apakah pemanggil berhak melihat/mengubah target user tertentu (Least Privilege).
     */
    protected function authorizeTargetUser(Request $request, User $targetUser, string $action = 'view'): void
    {
        $caller = $request->user();
        $callerRole = $this->getActiveRole($request);

        // 1. Platform Owner memiliki akses penuh ke semua akun
        if ($callerRole === UserRole::PLATFORM_OWNER->value) {
            return;
        }

        // 2. Admin Owner: TIDAK BISA menyentuh Platform Owner atau sesama Admin Owner
        if ($callerRole === UserRole::ADMIN_OWNER->value) {
            if ($targetUser->hasRole(UserRole::PLATFORM_OWNER->value) || $targetUser->hasRole(UserRole::ADMIN_OWNER->value)) {
                abort(403, 'Admin Owner tidak memiliki wewenang mengelola akun setingkat atau di atasnya.');
            }
            return;
        }

        // 3. Owner Toko: HANYA berhak mengelola staf (admin_toko & kurir) dari toko miliknya sendiri
        if ($callerRole === UserRole::OWNER_TOKO->value) {
            $myTokoIds = $caller->tokos()->pluck('id')->toArray();

            // Cek apakah targetUser bertugas di salah satu toko milik caller
            $isStaff = $targetUser->staffTokos()->whereIn('tokos.id', $myTokoIds)->exists();

            if (!$isStaff) {
                abort(403, 'Owner Toko hanya berhak mengelola staf yang bertugas di tokonya sendiri.');
            }
            return;
        }

        abort(403, 'Aksi tidak diizinkan.');
    }

    /**
     * Jumlah Akun Menunggu Persetujuan (Badge Counter).
     * GET /api/management/pending-accounts/count
     */
    public function pendingAccountsCount(Request $request): JsonResponse
    {
        $this->authorizePlatformOwnerPermission($request, 'accounts.approve');

        $count = User::where('status', 'pending_approval')->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Daftar Akun Menunggu Persetujuan.
     * GET /api/management/pending-accounts
     */
    public function pendingAccounts(Request $request): JsonResponse
    {
        $this->authorizePlatformOwnerPermission($request, 'accounts.approve');

        $query = User::with(['roles', 'profile'])
            ->where('status', 'pending_approval');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));
        $users = $query->latest('id')->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Setujui Pendaftaran Akun Pengguna.
     * POST /api/management/users/{id}/approve
     */
    public function approveUser(Request $request, int $id): JsonResponse
    {
        $caller = $this->authorizePlatformOwnerPermission($request, 'accounts.approve');
        $targetUser = User::findOrFail($id);

        if ($targetUser->status === 'active') {
            return response()->json([
                'message' => 'Akun ini sudah aktif sebelumnya.',
                'user' => $targetUser,
            ], 200);
        }

        $oldStatus = $targetUser->status;
        $targetUser->status = 'active';
        $targetUser->rejection_reason = null;
        $targetUser->save();

        // Kirim email persetujuan via queue
        try {
            Mail::to($targetUser->email)->queue(new AccountApprovedMailable($targetUser));
        } catch (\Throwable $e) {}

        // Notifikasi in-app ke pengguna
        try {
            $targetUser->notify(new PusatLaundryNotification(
                notificationType: 'account_approved',
                title: 'Pendaftaran Akun Disetujui',
                message: 'Selamat! Akun Pusat Laundry Anda telah disetujui. Anda sekarang dapat menggunakan seluruh layanan.',
                actionUrl: '/customer'
            ));
        } catch (\Throwable $e) {}

        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_APPROVED',
            targetType: User::class,
            targetId: $targetUser->id,
            beforeState: ['status' => $oldStatus],
            afterState: ['status' => 'active']
        );

        return response()->json([
            'message' => "Akun {$targetUser->name} ({$targetUser->email}) berhasil disetujui.",
            'user' => $targetUser->fresh(),
        ]);
    }

    /**
     * Tolak Pendaftaran Akun Pengguna (Alasan Wajib).
     * POST /api/management/users/{id}/reject
     */
    public function rejectUser(Request $request, int $id): JsonResponse
    {
        $caller = $this->authorizePlatformOwnerPermission($request, 'accounts.approve');
        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $reason = trim($validated['reason']);
        $oldStatus = $targetUser->status;

        $targetUser->status = 'rejected';
        $targetUser->rejection_reason = $reason;
        $targetUser->save();

        // Cabut semua token jika ada
        $targetUser->tokens()->delete();

        // Kirim email penolakan dengan alasan
        try {
            Mail::to($targetUser->email)->queue(new AccountRejectedMailable($targetUser, $reason));
        } catch (\Throwable $e) {}

        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_REJECTED',
            targetType: User::class,
            targetId: $targetUser->id,
            beforeState: ['status' => $oldStatus],
            afterState: ['status' => 'rejected', 'reason' => $reason]
        );

        return response()->json([
            'message' => "Pendaftaran akun {$targetUser->name} telah ditolak.",
            'user' => $targetUser->fresh(),
        ]);
    }

    /**
     * Setujui Akun Secara Massal (Bulk Approve).
     * POST /api/management/users/bulk-approve
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $caller = $this->authorizePlatformOwnerPermission($request, 'accounts.approve');

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:users,id',
        ]);

        $users = User::whereIn('id', $validated['ids'])
            ->where('status', 'pending_approval')
            ->get();

        $approvedCount = 0;
        foreach ($users as $user) {
            $user->status = 'active';
            $user->rejection_reason = null;
            $user->save();

            try {
                Mail::to($user->email)->queue(new AccountApprovedMailable($user));
            } catch (\Throwable $e) {}

            AuditLog::record(
                actorId: $caller->id,
                action: 'USER_APPROVED',
                targetType: User::class,
                targetId: $user->id,
                beforeState: ['status' => 'pending_approval'],
                afterState: ['status' => 'active']
            );

            $approvedCount++;
        }

        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_BULK_APPROVED',
            targetType: User::class,
            targetId: null,
            beforeState: null,
            afterState: ['approved_count' => $approvedCount, 'user_ids' => $users->pluck('id')->toArray()]
        );

        return response()->json([
            'message' => "Berhasil menyetujui {$approvedCount} akun pengguna secara massal.",
            'approved_count' => $approvedCount,
        ]);
    }

    /**
     * Tolak Akun Secara Massal (Bulk Reject).
     * POST /api/management/users/bulk-reject
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $caller = $this->authorizePlatformOwnerPermission($request, 'accounts.approve');

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:users,id',
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi untuk penolakan massal.',
        ]);

        $reason = trim($validated['reason']);
        $users = User::whereIn('id', $validated['ids'])
            ->whereIn('status', ['pending_approval', 'pending_email'])
            ->get();

        $rejectedCount = 0;
        foreach ($users as $user) {
            $oldStatus = $user->status;
            $user->status = 'rejected';
            $user->rejection_reason = $reason;
            $user->save();
            $user->tokens()->delete();

            try {
                Mail::to($user->email)->queue(new AccountRejectedMailable($user, $reason));
            } catch (\Throwable $e) {}

            AuditLog::record(
                actorId: $caller->id,
                action: 'USER_REJECTED',
                targetType: User::class,
                targetId: $user->id,
                beforeState: ['status' => $oldStatus],
                afterState: ['status' => 'rejected', 'reason' => $reason]
            );

            $rejectedCount++;
        }

        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_BULK_REJECTED',
            targetType: User::class,
            targetId: null,
            beforeState: null,
            afterState: ['rejected_count' => $rejectedCount, 'user_ids' => $users->pluck('id')->toArray(), 'reason' => $reason]
        );

        return response()->json([
            'message' => "Berhasil menolak {$rejectedCount} pendaftaran akun.",
            'rejected_count' => $rejectedCount,
        ]);
    }

    /**
     * Hapus Akun Pengguna (Soft Delete - Konfirmasi Dua Langkah).
     * DELETE /api/management/users/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $caller = $this->authorizePlatformOwnerPermission($request, 'accounts.manage');
        $targetUser = User::findOrFail($id);

        // 1. Dilarang menghapus akun sendiri
        if ($caller->id === $targetUser->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        // 2. Dilarang menghapus akun Platform Owner lewat antarmuka web
        if ($targetUser->hasRole(UserRole::PLATFORM_OWNER->value)) {
            return response()->json([
                'message' => 'Akun Platform Owner tidak dapat dihapus melalui antarmuka web.',
            ], 422);
        }

        // Cabut seluruh sesi aktif seketika
        $tokensCount = $targetUser->tokens()->count();
        $targetUser->tokens()->delete();

        // Lakukan Soft Delete
        $targetUser->delete();

        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_SOFT_DELETED',
            targetType: User::class,
            targetId: $targetUser->id,
            beforeState: ['email' => $targetUser->email, 'status' => $targetUser->status],
            afterState: ['deleted_at' => now(), 'revoked_tokens' => $tokensCount]
        );

        return response()->json([
            'message' => "Akun {$targetUser->name} ({$targetUser->email}) berhasil dihapus (soft delete).",
        ]);
    }

    /**
     * Ambil Pengaturan Bisnis Pendaftaran Akun.
     * GET /api/management/settings
     */
    public function getSettings(Request $request): JsonResponse
    {
        $this->authorizePlatformOwnerPermission($request, 'accounts.manage');

        return response()->json([
            'account_approval_mode' => BusinessSetting::getValue('account_approval_mode', 'wajib'),
        ]);
    }

    /**
     * Perbarui Pengaturan Bisnis Pendaftaran Akun.
     * PUT /api/management/settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $caller = $this->authorizePlatformOwnerPermission($request, 'accounts.manage');

        $validated = $request->validate([
            'account_approval_mode' => 'required|in:wajib,otomatis',
        ], [
            'account_approval_mode.in' => 'Pilihan mode persetujuan harus "wajib" atau "otomatis".',
        ]);

        $oldMode = BusinessSetting::getValue('account_approval_mode', 'wajib');
        $newMode = $validated['account_approval_mode'];

        BusinessSetting::setValue(
            'account_approval_mode',
            $newMode,
            'Mode persetujuan pendaftaran akun baru: wajib (manual) atau otomatis'
        );

        AuditLog::record(
            actorId: $caller->id,
            action: 'BUSINESS_SETTING_UPDATED',
            targetType: BusinessSetting::class,
            targetId: null,
            beforeState: ['account_approval_mode' => $oldMode],
            afterState: ['account_approval_mode' => $newMode]
        );

        return response()->json([
            'message' => "Pengaturan persetujuan akun berhasil diubah menjadi: {$newMode}.",
            'account_approval_mode' => $newMode,
        ]);
    }

    /**
     * List Akun dengan Filter, Pencarian, & Pagination ter-scope.
     * GET /api/management/users
     */
    public function index(Request $request): JsonResponse
    {
        $callerRole = $this->authorizeManagementAccess($request);
        $caller = $request->user();

        $query = User::with(['roles', 'profile', 'staffTokos:id,nama']);

        // Scope berdasarkan role pemanggil
        if ($callerRole === UserRole::PLATFORM_OWNER->value) {
            // Platform owner melihat seluruh user
        } elseif ($callerRole === UserRole::ADMIN_OWNER->value) {
            // Hanya melihat customer, kurir, owner_toko, admin_toko
            $query->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', [UserRole::PLATFORM_OWNER->value, UserRole::ADMIN_OWNER->value]);
            });
        } elseif ($callerRole === UserRole::OWNER_TOKO->value) {
            // Hanya staf toko milik owner
            $myTokoIds = $caller->tokos()->pluck('id')->toArray();
            $query->whereHas('staffTokos', function ($q) use ($myTokoIds) {
                $q->whereIn('tokos.id', $myTokoIds);
            });
        }

        // Filter: Status (active / pending_approval / pending_email / rejected / disabled)
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter: Role tertentu
        if ($request->filled('role')) {
            $targetRole = $request->query('role');
            $query->whereHas('roles', fn($q) => $q->where('name', $targetRole));
        }

        // Filter: Pencarian (Nama / Email)
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));
        $users = $query->latest('id')->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Detail Akun & Ringkasan Aktivitas.
     * GET /api/management/users/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeManagementAccess($request);
        $targetUser = User::with(['roles', 'profile', 'staffTokos:id,nama', 'tokos:id,nama,status'])->findOrFail($id);

        $this->authorizeTargetUser($request, $targetUser, 'view');

        // Ringkasan pesanan & dispute
        $orderCount = DB::table('orders')->where('customer_id', $targetUser->id)->count();
        $disputeCount = DB::table('disputes')->where('customer_id', $targetUser->id)->count();

        return response()->json([
            'user' => $targetUser,
            'summary' => [
                'order_count' => $orderCount,
                'dispute_count' => $disputeCount,
                'active_sessions_count' => $targetUser->tokens()->count(),
            ],
        ]);
    }

    /**
     * Ubah Status Akun (Aktif / Dinonaktifkan).
     * PATCH /api/management/users/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->authorizeManagementAccess($request);
        $caller = $request->user();
        $targetUser = User::findOrFail($id);

        $this->authorizeTargetUser($request, $targetUser, 'update_status');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'disabled'])],
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $targetUser->status;

        // Validasi: Tidak boleh menonaktifkan akun sendiri
        if ($caller->id === $targetUser->id && $newStatus === 'disabled') {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 422);
        }

        // Validasi: Tidak boleh menonaktifkan akun Platform Owner aktif terakhir
        if ($targetUser->hasRole(UserRole::PLATFORM_OWNER->value) && $newStatus === 'disabled') {
            $activePlatformOwners = User::role(UserRole::PLATFORM_OWNER->value)
                ->where('status', 'active')
                ->count();

            if ($activePlatformOwners <= 1) {
                return response()->json([
                    'message' => 'Tidak dapat menonaktifkan akun Platform Owner aktif terakhir di sistem.',
                ], 422);
            }
        }

        $targetUser->status = $newStatus;
        $targetUser->save();

        // Jika dinonaktifkan: Segera cabut seluruh token akses yang aktif (immediate lockout)
        if ($newStatus === 'disabled') {
            $targetUser->tokens()->delete();
        }

        // Catat Audit Log
        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_STATUS_TOGGLED',
            targetType: get_class($targetUser),
            targetId: $targetUser->id,
            beforeState: ['status' => $oldStatus],
            afterState: ['status' => $newStatus]
        );

        // Kirim Notifikasi ke User
        $targetUser->notify(new PusatLaundryNotification(
            notificationType: 'account_status_changed',
            title: 'Status Akun Diperbarui',
            message: $newStatus === 'active'
                ? 'Akun Pusat Laundry Anda telah diaktifkan kembali oleh administrator.'
                : 'Akun Pusat Laundry Anda telah dinonaktifkan oleh administrator.',
            metadata: ['status' => $newStatus]
        ));

        return response()->json([
            'message' => "Status akun {$targetUser->name} berhasil diubah menjadi {$newStatus}.",
            'user' => $targetUser->fresh(),
        ]);
    }

    /**
     * Ubah Role Akun (dengan pencegahan eskalasi hak akses / Privilege Escalation).
     * PATCH /api/management/users/{id}/role
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $this->authorizeManagementAccess($request);
        $caller = $request->user();
        $callerRole = $this->getActiveRole($request);
        $targetUser = User::findOrFail($id);

        $this->authorizeTargetUser($request, $targetUser, 'update_role');

        $validated = $request->validate([
            'role' => ['required', Rule::in([
                UserRole::PLATFORM_OWNER->value,
                UserRole::ADMIN_OWNER->value,
                UserRole::OWNER_TOKO->value,
                UserRole::ADMIN_TOKO->value,
                UserRole::KURIR->value,
                UserRole::CUSTOMER->value,
            ])],
        ]);

        $newRole = $validated['role'];
        $oldRoles = $targetUser->getRoleNames()->toArray();

        // 1. Tidak boleh mengubah role diri sendiri
        if ($caller->id === $targetUser->id) {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah role akun Anda sendiri.',
            ], 422);
        }

        // 2. Role Platform Owner TIDAK BISA diberikan lewat UI (hanya lewat owner:bootstrap)
        if ($newRole === UserRole::PLATFORM_OWNER->value) {
            return response()->json([
                'message' => 'Role Platform Owner hanya dapat ditetapkan lewat perintah artisan owner:bootstrap.',
            ], 403);
        }

        // 3. Platform Owner tidak boleh diturunkan role-nya jika dia adalah Platform Owner aktif terakhir
        if ($targetUser->hasRole(UserRole::PLATFORM_OWNER->value)) {
            $activePlatformOwners = User::role(UserRole::PLATFORM_OWNER->value)
                ->where('status', 'active')
                ->count();

            if ($activePlatformOwners <= 1) {
                return response()->json([
                    'message' => 'Tidak dapat mengubah role akun Platform Owner aktif terakhir di sistem.',
                ], 422);
            }
        }

        // 4. Admin Owner TIDAK BOLEH memberikan role Platform Owner atau Admin Owner
        if ($callerRole === UserRole::ADMIN_OWNER->value) {
            if (in_array($newRole, [UserRole::PLATFORM_OWNER->value, UserRole::ADMIN_OWNER->value])) {
                return response()->json([
                    'message' => 'Admin Owner tidak berwenang memberikan role Platform Owner atau Admin Owner.',
                ], 403);
            }
        }

        // 5. Owner Toko HANYA boleh mengatur antara Admin Toko atau Kurir untuk staf tokonya
        if ($callerRole === UserRole::OWNER_TOKO->value) {
            if (!in_array($newRole, [UserRole::ADMIN_TOKO->value, UserRole::KURIR->value])) {
                return response()->json([
                    'message' => 'Owner Toko hanya berhak mengubah role staf menjadi Admin Toko atau Kurir.',
                ], 403);
            }
        }

        // Tetapkan role baru
        $targetUser->syncRoles([$newRole]);

        // Catat Audit Log
        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_ROLE_CHANGED',
            targetType: get_class($targetUser),
            targetId: $targetUser->id,
            beforeState: ['roles' => $oldRoles],
            afterState: ['roles' => [$newRole]]
        );

        // Notifikasi ke user
        $targetUser->notify(new PusatLaundryNotification(
            notificationType: 'role_changed',
            title: 'Perubahan Peran Akun',
            message: "Peran utama akun Anda telah diperbarui menjadi {$newRole}.",
            metadata: ['role' => $newRole]
        ));

        return response()->json([
            'message' => "Role untuk akun {$targetUser->name} berhasil diperbarui menjadi {$newRole}.",
            'roles' => $targetUser->getRoleNames(),
        ]);
    }

    /**
     * Cabut Seluruh Sesi Login Aktif (Revoke Sanctum Tokens).
     * POST /api/management/users/{id}/revoke-sessions
     */
    public function revokeSessions(Request $request, int $id): JsonResponse
    {
        $this->authorizeManagementAccess($request);
        $caller = $request->user();
        $targetUser = User::findOrFail($id);

        $this->authorizeTargetUser($request, $targetUser, 'revoke_sessions');

        $tokensCount = $targetUser->tokens()->count();
        $targetUser->tokens()->delete();

        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_SESSIONS_REVOKED',
            targetType: get_class($targetUser),
            targetId: $targetUser->id,
            beforeState: ['revoked_tokens_count' => $tokensCount],
            afterState: ['revoked_tokens_count' => 0]
        );

        return response()->json([
            'message' => "Seluruh sesi login aktif ({$tokensCount} sesi) untuk {$targetUser->name} berhasil dicabut.",
        ]);
    }

    /**
     * Picu Pengiriman Tautan Reset Password oleh Administrator.
     * POST /api/management/users/{id}/reset-password
     */
    public function triggerResetPassword(Request $request, int $id): JsonResponse
    {
        $this->authorizeManagementAccess($request);
        $caller = $request->user();
        $targetUser = User::findOrFail($id);

        $this->authorizeTargetUser($request, $targetUser, 'reset_password');

        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $targetUser->email],
            [
                'token' => $hashedToken,
                'created_at' => now(),
            ]
        );

        $resetUrl = config('app.frontend_url', 'http://localhost:5173')
            . '/reset-password?token=' . $plainToken
            . '&email=' . urlencode($targetUser->email);

        Mail::to($targetUser->email)->queue(new ResetPasswordMailable($targetUser, $resetUrl));

        AuditLog::record(
            actorId: $caller->id,
            action: 'USER_PASSWORD_RESET_TRIGGERED',
            targetType: get_class($targetUser),
            targetId: $targetUser->id,
            beforeState: null,
            afterState: ['email' => $targetUser->email]
        );

        return response()->json([
            'message' => "Tautan reset password berhasil dikirim ke email {$targetUser->email}.",
        ]);
    }

    /**
     * Daftar Undangan Staf yang Sedang Berjalan.
     * GET /api/management/invitations
     */
    public function listInvitations(Request $request): JsonResponse
    {
        $callerRole = $this->authorizeManagementAccess($request);
        $caller = $request->user();

        $query = StaffInvitation::with(['toko:id,nama', 'inviter:id,name']);

        if ($callerRole === UserRole::PLATFORM_OWNER->value) {
            // Semua undangan
        } elseif ($callerRole === UserRole::ADMIN_OWNER->value) {
            // Undangan kecuali role platform_owner
            $query->where('role', '!=', UserRole::PLATFORM_OWNER->value);
        } elseif ($callerRole === UserRole::OWNER_TOKO->value) {
            // Hanya undangan untuk tokonya sendiri
            $myTokoIds = $caller->tokos()->pluck('id')->toArray();
            $query->whereIn('toko_id', $myTokoIds);
        }

        $invitations = $query->latest('id')->paginate(15);

        return response()->json($invitations);
    }
}
