<?php

namespace App\Http\Controllers\Api\AdminOwner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Toko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TokoApprovalController extends Controller
{
    /**
     * Menampilkan semua pengajuan toko untuk ditinjau oleh Admin Owner / Platform Owner.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Toko::class);

        $query = Toko::with(['owner:id,name,email,avatar', 'tarifs'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $tokos = $query->paginate(20);

        return response()->json($tokos);
    }

    /**
     * Mengubah status toko: submitted -> approved / rejected -> suspended.
     * WAJIB tercatat di audit_logs secara immutable.
     */
    public function updateStatus(Request $request, Toko $toko): JsonResponse
    {
        Gate::authorize('changeStatus', $toko);

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,suspended,submitted',
            'reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $toko->status;
        $newStatus = $validated['status'];
        $reason = $validated['reason'] ?? null;

        // Update status toko
        $toko->update([
            'status' => $newStatus,
        ]);

        // Catat Audit Trail ke tabel audit_logs (Immutable)
        $actionName = match ($newStatus) {
            'approved' => 'APPROVE_TOKO',
            'rejected' => 'REJECT_TOKO',
            'suspended' => 'SUSPEND_TOKO',
            default => 'UPDATE_STATUS_TOKO',
        };

        $auditLog = AuditLog::record(
            actorId: $request->user()->id,
            action: $actionName,
            targetType: Toko::class,
            targetId: $toko->id,
            beforeState: ['status' => $oldStatus],
            afterState: [
                'status' => $newStatus,
                'reason' => $reason,
                'toko_nama' => $toko->nama,
            ]
        );

        return response()->json([
            'message' => "Status toko berhasil diubah menjadi {$newStatus}.",
            'toko' => $toko->fresh()->load('owner:id,name,email'),
            'audit_log_id' => $auditLog->id,
        ]);
    }
}
