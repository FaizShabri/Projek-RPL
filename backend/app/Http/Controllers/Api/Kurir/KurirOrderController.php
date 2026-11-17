<?php

namespace App\Http\Controllers\Api\Kurir;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KurirOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Daftar tugas penjemputan & pengantaran yang tersedia untuk diambil kurir.
     */
    public function availableTasks(Request $request): JsonResponse
    {
        $orders = Order::where(function ($q) {
                // Tugas jemput yang belum diambil
                $q->where('status', OrderStatus::MENUNGGU_KURIR->value)
                  ->whereNull('kurir_id');
            })
            ->orWhere(function ($q) use ($request) {
                // Tugas antar yang siap dikirim
                $q->where('status', OrderStatus::SIAP_DIANTAR->value)
                  ->where(function ($sub) use ($request) {
                      $sub->whereNull('kurir_id')
                          ->orWhere('kurir_id', $request->user()->id);
                  });
            })
            ->with([
                'toko:id,nama,alamat,latitude,longitude',
                'customer:id,name,avatar',
                'tarif:id,jenis_layanan,harga',
            ])
            ->latest()
            ->paginate(15);

        return response()->json($orders);
    }

    /**
     * Daftar tugas aktif & riwayat tugas milik kurir yang sedang login.
     */
    public function myTasks(Request $request): JsonResponse
    {
        $query = Order::where('kurir_id', $request->user()->id)
            ->with([
                'toko:id,nama,alamat,latitude,longitude',
                'customer:id,name,avatar',
                'tarif:id,jenis_layanan,harga',
                'statusHistories',
            ])
            ->latest();

        if ($filter = $request->query('type')) {
            if ($filter === 'active') {
                $query->whereNotIn('status', [OrderStatus::SELESAI->value, OrderStatus::DIBATALKAN->value]);
            } elseif ($filter === 'completed') {
                $query->where('status', OrderStatus::SELESAI->value);
            }
        }

        $tasks = $query->paginate(15);

        return response()->json($tasks);
    }

    /**
     * Kurir menerima / mengambil tugas.
     */
    public function acceptTask(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $activeRole = $request->header('X-Active-Role') ?: UserRole::KURIR->value;

        $updated = $this->orderService->acceptPickupTask($order, $request->user(), $activeRole);

        return response()->json([
            'message' => 'Tugas berhasil diterima. Silakan jemput pakaian di lokasi customer.',
            'order'   => $updated,
        ]);
    }

    /**
     * Kurir melepas tugas kembali ke antrean publik.
     */
    public function releaseTask(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $activeRole = $request->header('X-Active-Role') ?: UserRole::KURIR->value;

        $updated = $this->orderService->releaseTask($order, $request->user(), $activeRole);

        return response()->json([
            'message' => 'Tugas berhasil dilepas.',
            'order'   => $updated,
        ]);
    }

    /**
     * Kurir memperbarui status pesanan (dijemput, di_toko, diantar, cod_selesai, selesai).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'required|string',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);
        $activeRole = $request->header('X-Active-Role') ?: UserRole::KURIR->value;

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
