<?php

namespace App\Http\Controllers\Api\PlatformOwner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Toko;
use App\Models\User;
use App\Policies\FinancePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FinanceController extends Controller
{
    /**
     * Overview seluruh sistem platform (Khusus Platform Owner).
     */
    public function getOverview(Request $request): JsonResponse
    {
        Gate::authorize('manageFinances');

        $totalUsers = User::count();
        $totalTokos = Toko::count();
        $tokosByStatus = [
            'approved' => Toko::where('status', 'approved')->count(),
            'submitted' => Toko::where('status', 'submitted')->count(),
            'rejected' => Toko::where('status', 'rejected')->count(),
            'suspended' => Toko::where('status', 'suspended')->count(),
        ];

        $totalOrders = Order::count();
        $completedOrdersCount = Order::whereIn('status', ['selesai', 'cod_selesai'])->count();
        $activeOrdersCount = Order::whereNotIn('status', ['selesai', 'cod_selesai', 'dibatalkan'])->count();

        // Total GMV (Gross Merchandise Value) dari order selesai
        $totalGmv = (float) Order::whereIn('status', ['selesai', 'cod_selesai'])->sum('total_biaya');
        $commissionRate = 10.0; // 10%
        $totalCommission = round(($totalGmv * $commissionRate) / 100, 2);
        $totalStorePayouts = $totalGmv - $totalCommission;

        $recentOrders = Order::with(['customer:id,name', 'toko:id,nama'])
            ->latest()
            ->take(5)
            ->get();

        $recentAuditLogs = AuditLog::with('actor:id,name,email')
            ->latest('created_at')
            ->take(5)
            ->get();

        return response()->json([
            'overview' => [
                'total_users' => $totalUsers,
                'total_tokos' => $totalTokos,
                'tokos_by_status' => $tokosByStatus,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrdersCount,
                'active_orders' => $activeOrdersCount,
                'total_gmv' => $totalGmv,
                'commission_rate_percentage' => $commissionRate,
                'total_commission' => $totalCommission,
                'total_store_payouts' => $totalStorePayouts,
            ],
            'recent_orders' => $recentOrders,
            'recent_audit_logs' => $recentAuditLogs,
        ]);
    }

    /**
     * Endpoint pengaturan komisi platform & keuangan inti.
     * 
     * PERBEDAAN POLICY:
     * - Platform Owner (memiliki permission 'manage_finances') -> 200 OK.
     * - Admin Owner (TIDAK memiliki permission 'manage_finances') -> 403 Forbidden.
     */
    public function getCommissionSettings(Request $request): JsonResponse
    {
        Gate::authorize('manageCommissions');

        return response()->json([
            'message' => 'Akses pengaturan komisi dan keuangan inti platform diizinkan (Hanya Platform Owner).',
            'platform_finance' => [
                'commission_rate_percentage' => 10.0,
                'min_settlement_amount' => 50000,
                'payment_flow' => 'COD / Cash Only',
                'managed_by' => $request->user()->name,
            ],
        ]);
    }

    /**
     * Mengubah persentase komisi platform.
     */
    public function updateCommissionSettings(Request $request): JsonResponse
    {
        Gate::authorize('manageCommissions');

        $validated = $request->validate([
            'commission_rate_percentage' => 'required|numeric|between:0,100',
            'min_settlement_amount' => 'required|numeric|min:0',
        ]);

        AuditLog::record(
            actorId: $request->user()->id,
            action: 'UPDATE_COMMISSION_SETTINGS',
            targetType: 'PlatformFinance',
            targetId: null,
            beforeState: ['commission_rate_percentage' => 10.0],
            afterState: $validated
        );

        return response()->json([
            'message' => 'Pengaturan komisi platform berhasil diperbarui.',
            'settings' => $validated,
        ]);
    }

    /**
     * Laporan keuangan & settlement semua toko mitra.
     */
    public function getAllSettlements(Request $request): JsonResponse
    {
        Gate::authorize('manageSettlements');

        $tokos = Toko::with(['owner:id,name,email'])
            ->withCount(['orders as completed_orders_count' => function ($query) {
                $query->whereIn('status', ['selesai', 'cod_selesai']);
            }])
            ->get()
            ->map(function ($toko) {
                $grossSales = (float) Order::where('toko_id', $toko->id)
                    ->whereIn('status', ['selesai', 'cod_selesai'])
                    ->sum('total_biaya');

                $platformFee = round(($grossSales * 10.0) / 100, 2);
                $netPayout = $grossSales - $platformFee;

                return [
                    'toko_id' => $toko->id,
                    'nama_toko' => $toko->nama,
                    'status_toko' => $toko->status,
                    'owner_name' => $toko->owner?->name,
                    'owner_email' => $toko->owner?->email,
                    'completed_orders_count' => $toko->completed_orders_count,
                    'gross_sales' => $grossSales,
                    'platform_fee_percentage' => 10.0,
                    'platform_fee_amount' => $platformFee,
                    'net_payout' => $netPayout,
                    'settlement_status' => $netPayout >= 50000 ? 'ready' : ($netPayout > 0 ? 'below_threshold' : 'settled'),
                ];
            });

        return response()->json([
            'settlements' => $tokos,
            'summary' => [
                'total_tokos' => $tokos->count(),
                'total_gross_all' => $tokos->sum('gross_sales'),
                'total_commission_all' => $tokos->sum('platform_fee_amount'),
                'total_payable_all' => $tokos->sum('net_payout'),
            ],
        ]);
    }

    /**
     * Proses settlement / payout ke toko spesifik.
     */
    public function processPayout(Request $request, int $tokoId): JsonResponse
    {
        Gate::authorize('manageSettlements');

        $toko = Toko::findOrFail($tokoId);

        $grossSales = (float) Order::where('toko_id', $toko->id)
            ->whereIn('status', ['selesai', 'cod_selesai'])
            ->sum('total_biaya');

        $netPayout = round($grossSales * 0.9, 2);

        AuditLog::record(
            actorId: $request->user()->id,
            action: 'PROCESS_SETTLEMENT_PAYOUT',
            targetType: Toko::class,
            targetId: $toko->id,
            beforeState: ['status' => 'pending_settlement'],
            afterState: [
                'toko_nama' => $toko->nama,
                'payout_amount' => $netPayout,
                'processed_at' => now()->toIso8601String(),
            ]
        );

        return response()->json([
            'message' => "Settlement dana payout sebesar Rp " . number_format($netPayout, 0, ',', '.') . " untuk toko {$toko->nama} berhasil diproses.",
            'payout_amount' => $netPayout,
            'toko' => $toko->nama,
        ]);
    }
}
