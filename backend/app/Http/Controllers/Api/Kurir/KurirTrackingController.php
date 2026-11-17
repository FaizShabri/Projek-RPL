<?php

namespace App\Http\Controllers\Api\Kurir;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\KurirLocationLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KurirTrackingController extends Controller
{
    /**
     * Status order yang aktif untuk tracking (kurir sedang bergerak membawa laundry).
     */
    private const TRACKABLE_STATUSES = [
        OrderStatus::DIJEMPUT,
        OrderStatus::DIANTAR,
    ];

    /**
     * Menerima ping lokasi GPS dari kurir.
     *
     * Endpoint ini dirancang seringan mungkin karena akan dipanggil
     * dengan frekuensi tinggi (setiap beberapa detik).
     */
    public function ping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'order_id'  => ['required', 'exists:orders,id'],
        ]);

        $order = Order::select('id', 'kurir_id', 'status')->find($validated['order_id']);

        // Pastikan kurir yang mengirim ping adalah kurir yang ditugaskan pada order ini
        if ($order->kurir_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error'   => 'UNAUTHORIZED_KURIR',
                'message' => 'Anda bukan kurir yang ditugaskan pada order ini.',
            ], 403);
        }

        // Pastikan order dalam status yang membutuhkan tracking
        if (! in_array($order->status, self::TRACKABLE_STATUSES)) {
            return response()->json([
                'success' => false,
                'error'   => 'ORDER_NOT_TRACKABLE',
                'message' => 'Order tidak dalam status yang membutuhkan tracking lokasi.',
            ], 422);
        }

        // Insert log lokasi — lightweight, tanpa event/observer
        KurirLocationLog::create([
            'kurir_id'  => $request->user()->id,
            'order_id'  => $order->id,
            'latitude'  => $validated['latitude'],
            'longitude' => $validated['longitude'],
        ]);

        return response()->json([
            'success' => true,
            'tracked' => true,
        ]);
    }
}
