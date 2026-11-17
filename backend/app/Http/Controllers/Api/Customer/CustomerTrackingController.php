<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\KurirLocationLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTrackingController extends Controller
{
    /**
     * Status order yang bisa di-track oleh customer.
     */
    private const TRACKABLE_STATUSES = [
        OrderStatus::DIJEMPUT,
        OrderStatus::DIANTAR,
    ];

    /**
     * Ambang batas waktu (detik) untuk menentukan status tracking.
     */
    private const STALE_THRESHOLD_SECONDS   = 30;
    private const OFFLINE_THRESHOLD_SECONDS = 120;

    /**
     * Customer melihat lokasi kurir secara real-time (polling).
     */
    public function getKurirLocation(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with('kurir:id,name')->find($orderId);

        if (! $order) {
            return response()->json([
                'success' => false,
                'error'   => 'ORDER_NOT_FOUND',
                'message' => 'Order tidak ditemukan.',
            ], 404);
        }

        // Pastikan hanya customer pemilik order yang bisa melihat tracking
        if ($order->customer_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error'   => 'UNAUTHORIZED_CUSTOMER',
                'message' => 'Anda tidak memiliki akses ke order ini.',
            ], 403);
        }

        // Jika order tidak dalam status trackable, kembalikan tracking_active: false
        if (! in_array($order->status, self::TRACKABLE_STATUSES)) {
            return response()->json([
                'tracking_active' => false,
                'order_status'    => $order->status->value,
                'message'         => 'Tracking tidak aktif untuk status order saat ini.',
            ]);
        }

        // Ambil log lokasi terakhir untuk order ini
        $latestLog = KurirLocationLog::where('order_id', $orderId)
            ->latest('created_at')
            ->first();

        // Tidak ada data lokasi sama sekali
        if (! $latestLog) {
            return response()->json([
                'tracking_active' => true,
                'latitude'        => null,
                'longitude'       => null,
                'updated_at'      => null,
                'status'          => 'offline',
                'order_status'    => $order->status->value,
                'kurir_name'      => $order->kurir?->name,
            ]);
        }

        // Hitung staleness berdasarkan selisih waktu
        $secondsAgo = $latestLog->created_at->diffInSeconds(now());

        if ($secondsAgo >= self::OFFLINE_THRESHOLD_SECONDS) {
            $trackingStatus = 'offline';
        } elseif ($secondsAgo >= self::STALE_THRESHOLD_SECONDS) {
            $trackingStatus = 'stale';
        } else {
            $trackingStatus = 'live';
        }

        return response()->json([
            'tracking_active' => true,
            'latitude'        => $latestLog->latitude,
            'longitude'       => $latestLog->longitude,
            'updated_at'      => $latestLog->created_at->toIso8601String(),
            'status'          => $trackingStatus,
            'order_status'    => $order->status->value,
            'kurir_name'      => $order->kurir?->name,
        ]);
    }
}
