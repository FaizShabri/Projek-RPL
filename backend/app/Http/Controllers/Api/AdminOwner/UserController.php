<?php

namespace App\Http\Controllers\Api\AdminOwner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Menampilkan daftar semua pengguna platform.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'profile'])->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $users = $query->paginate(20);

        return response()->json($users);
    }

    /**
     * Mengubah status aktif / suspend pengguna.
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $targetUser = User::findOrFail($id);

        // Jangan izinkan suspend diri sendiri
        if ($targetUser->id === $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah status akun Anda sendiri.',
            ], 422);
        }

        $newStatus = $targetUser->status === 'suspended' ? 'active' : 'suspended';
        $oldStatus = $targetUser->status;

        $targetUser->update(['status' => $newStatus]);

        AuditLog::record(
            actorId: $request->user()->id,
            action: $newStatus === 'suspended' ? 'SUSPEND_USER' : 'ACTIVATE_USER',
            targetType: User::class,
            targetId: $targetUser->id,
            beforeState: ['status' => $oldStatus],
            afterState: [
                'status' => $newStatus,
                'user_name' => $targetUser->name,
                'user_email' => $targetUser->email,
            ]
        );

        return response()->json([
            'message' => "Pengguna {$targetUser->name} berhasil di-" . ($newStatus === 'suspended' ? 'suspend.' : 'aktifkan kembali.'),
            'user' => $targetUser->fresh(['roles', 'profile']),
        ]);
    }
}
