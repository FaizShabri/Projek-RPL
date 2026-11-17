<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\DisputeOpened;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeSubmissionController extends Controller
{
    /**
     * Buka dispute baru untuk suatu order oleh Customer atau Toko.
     */
    public function store(Request $request, int $orderId): JsonResponse
    {
        $user = $request->user();

        $order = Order::with('toko')->findOrFail($orderId);

        // Otorisasi: Customer pemilik order ATAU Owner/Staff toko terkait
        $isCustomer = $order->customer_id === $user->id;
        $isTokoOwner = $order->toko && $order->toko->owner_id === $user->id;
        $isTokoStaff = $order->toko && $order->toko->staffUsers()->where('user_id', $user->id)->exists();

        if (!$isCustomer && !$isTokoOwner && !$isTokoStaff) {
            return response()->json([
                'message' => 'Hanya pelanggan atau pengelola toko terkait yang dapat mengajukan komplain/dispute untuk pesanan ini.',
            ], 403);
        }

        // Cek apakah order sudah memiliki dispute yang sedang aktif (open / in_review)
        $hasActiveDispute = Dispute::where('order_id', $order->id)
            ->whereIn('status', ['open', 'in_review', 'under_review'])
            ->exists();

        if ($hasActiveDispute) {
            return response()->json([
                'message' => 'Pesanan ini sudah memiliki dispute yang sedang aktif dan dalam penanganan admin.',
            ], 422);
        }

        $validated = $request->validate([
            'alasan'    => 'required|string|max:255',
            'deskripsi' => 'required|string|min:10|max:3000',
        ]);

        $dispute = Dispute::create([
            'order_id'    => $order->id,
            'dibuka_oleh' => $user->id,
            'alasan'      => $validated['alasan'],
            'deskripsi'   => $validated['deskripsi'],
            'status'      => 'open',
        ]);

        // Catat ke Audit Log
        AuditLog::record(
            actorId: $user->id,
            action: 'CREATE_DISPUTE',
            targetType: Dispute::class,
            targetId: $dispute->id,
            beforeState: null,
            afterState: [
                'order_id'    => $order->id,
                'dibuka_oleh' => $user->id,
                'pelapor_role'=> $isCustomer ? 'customer' : 'toko',
                'alasan'      => $dispute->alasan,
                'deskripsi'   => $dispute->deskripsi,
                'status'      => 'open',
            ]
        );

        // Dispatch Event Notifikasi Dispute
        DisputeOpened::dispatch($dispute, $user);

        return response()->json([
            'message' => 'Dispute / komplain berhasil dibuka dan diteruskan ke Platform Owner / Admin.',
            'dispute' => $dispute->load(['pelapor:id,name,email', 'order:id,nomor_order,status']),
        ], 201);
    }

    /**
     * Tampilkan riwayat dispute untuk suatu pesanan.
     */
    public function index(Request $request, int $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::with('toko')->findOrFail($orderId);

        $isCustomer = $order->customer_id === $user->id;
        $isTokoOwner = $order->toko && $order->toko->owner_id === $user->id;
        $isTokoStaff = $order->toko && $order->toko->staffUsers()->where('user_id', $user->id)->exists();

        // Platform Owner / Admin Owner selalu diizinkan
        $isAdminOrOwner = in_array($user->currentRole, ['platform_owner', 'admin_owner']) 
            || ($user->roles && array_intersect(['platform_owner', 'admin_owner'], (array) $user->roles));

        if (!$isCustomer && !$isTokoOwner && !$isTokoStaff && !$isAdminOrOwner) {
            return response()->json([
                'message' => 'Anda tidak memiliki hak akses untuk melihat dispute pada pesanan ini.',
            ], 403);
        }

        $disputes = Dispute::where('order_id', $order->id)
            ->with(['pelapor:id,name,email,avatar'])
            ->latest()
            ->get();

        return response()->json([
            'disputes' => $disputes,
        ]);
    }
}
