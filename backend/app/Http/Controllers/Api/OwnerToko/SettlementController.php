<?php

namespace App\Http\Controllers\Api\OwnerToko;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Toko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    /**
     * Menampilkan ringkasan settlement dan penghasilan toko sendiri.
     */
    public function index(Request $request, Toko $toko): JsonResponse
    {
        $completedOrdersQuery = Order::where('toko_id', $toko->id)
            ->whereIn('status', ['selesai', 'cod_selesai']);

        $totalCompletedOrders = (clone $completedOrdersQuery)->count();
        $grossRevenue = (float) (clone $completedOrdersQuery)->sum('total_biaya');
        $platformCommissionRate = 10.0; // 10%
        $platformFee = round(($grossRevenue * $platformCommissionRate) / 100, 2);
        $netEarnings = $grossRevenue - $platformFee;

        // Histori transaksi completed terbaru
        $recentTransactions = (clone $completedOrdersQuery)
            ->with(['customer:id,name', 'tarif:id,jenis_layanan'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'toko' => [
                'id' => $toko->id,
                'nama' => $toko->nama,
            ],
            'settlement_summary' => [
                'total_completed_orders' => $totalCompletedOrders,
                'gross_revenue' => $grossRevenue,
                'platform_commission_rate' => $platformCommissionRate,
                'platform_fee_amount' => $platformFee,
                'net_earnings' => $netEarnings,
                'min_payout_threshold' => 50000,
                'can_request_payout' => $netEarnings >= 50000,
            ],
            'transactions' => $recentTransactions,
        ]);
    }
}
