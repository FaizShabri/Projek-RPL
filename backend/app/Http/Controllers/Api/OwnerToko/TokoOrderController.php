<?php

namespace App\Http\Controllers\Api\OwnerToko;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Toko;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokoOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Daftar pesanan yang masuk pada toko spesifik.
     */
    public function index(Request $request, Toko $toko): JsonResponse
    {
        $query = Order::where('toko_id', $toko->id)
            ->with([
                'customer:id,name,avatar',
                'tarif:id,jenis_layanan,harga',
                'kurir:id,name,avatar',
                'statusHistories',
                'disputes',
            ])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(15);

        return response()->json($orders);
    }

    /**
     * Toko mengonfirmasi order baru (menunggu_konfirmasi -> menunggu_kurir).
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $order = Order::with('toko')->findOrFail($id);
        $activeRole = $request->header('X-Active-Role') ?: UserRole::OWNER_TOKO->value;

        $updated = $this->orderService->confirmOrder($order, $request->user(), $activeRole);

        return response()->json([
            'message' => 'Pesanan berhasil dikonfirmasi. Tugas penjemputan ditawarkan ke kurir.',
            'order'   => $updated,
        ]);
    }

    /**
     * Input berat pakaian setelah ditimbang & sistem menghitung total biaya otomatis.
     * Transisi: di_toko -> menunggu_pembayaran_info.
     */
    public function weigh(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'berat' => 'required|numeric|min:0.01',
        ]);

        $order = Order::with(['toko', 'tarif'])->findOrFail($id);
        $activeRole = $request->header('X-Active-Role') ?: UserRole::OWNER_TOKO->value;

        $updated = $this->orderService->weighAndCalculateTotal(
            $order,
            (float) $validated['berat'],
            $request->user(),
            $activeRole
        );

        return response()->json([
            'message'     => 'Berat cucian berhasil diinput dan total biaya COD telah dihitung.',
            'total_biaya' => $updated->total_biaya,
            'order'       => $updated,
        ]);
    }

    /**
     * Toko memperbarui status pengerjaan (diproses, siap_diantar, dibatalkan).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'required|string',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $order = Order::with('toko')->findOrFail($id);
        $activeRole = $request->header('X-Active-Role') ?: UserRole::OWNER_TOKO->value;

        $updated = $this->orderService->transitionStatus(
            $order,
            $validated['status'],
            $request->user(),
            $activeRole,
            $validated['keterangan'] ?? null
        );

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui.',
            'order'   => $updated,
        ]);
    }
}
