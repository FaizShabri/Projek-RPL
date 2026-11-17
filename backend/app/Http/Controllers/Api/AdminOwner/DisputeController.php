<?php

namespace App\Http\Controllers\Api\AdminOwner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dispute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    /**
     * Menampilkan daftar semua komplain / dispute transaksi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Dispute::with([
            'pelapor:id,name,email,avatar',
            'order:id,nomor_order,status,total_biaya,customer_id,toko_id,kurir_id',
            'order.toko:id,nama',
            'order.customer:id,name,email',
            'order.kurir:id,name,email',
        ])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $disputes = $query->paginate(15);

        return response()->json($disputes);
    }

    /**
     * Memperbarui status penyelesaian dispute oleh admin.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $dispute = Dispute::with(['order', 'pelapor'])->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:open,in_review,under_review,resolved,rejected',
            'resolusi' => in_array($request->status, ['resolved', 'rejected'])
                ? 'required|string|min:5|max:1500'
                : 'nullable|string|max:1500',
        ], [
            'resolusi.required' => 'Catatan resolusi wajib diisi saat menyelesaikan atau menolak dispute.',
            'resolusi.min' => 'Catatan resolusi minimal 5 karakter.',
        ]);

        $oldStatus = $dispute->status;
        $oldResolusi = $dispute->resolusi;
        $dispute->update([
            'status' => $validated['status'],
            'resolusi' => $validated['resolusi'] ?? $dispute->resolusi,
        ]);

        $action = match ($validated['status']) {
            'resolved' => 'RESOLVE_DISPUTE',
            'rejected' => 'REJECT_DISPUTE',
            'in_review', 'under_review' => 'IN_REVIEW_DISPUTE',
            default => 'UPDATE_DISPUTE_STATUS',
        };

        // Catat ke Audit Log
        AuditLog::record(
            actorId: $request->user()->id,
            action: $action,
            targetType: Dispute::class,
            targetId: $dispute->id,
            beforeState: [
                'status' => $oldStatus,
                'resolusi' => $oldResolusi,
            ],
            afterState: [
                'status' => $dispute->status,
                'resolusi' => $dispute->resolusi,
                'order_id' => $dispute->order_id,
            ]
        );

        return response()->json([
            'message' => 'Status dispute berhasil diperbarui.',
            'dispute' => $dispute->fresh(['pelapor', 'order.toko', 'order.customer']),
        ]);
    }
}
