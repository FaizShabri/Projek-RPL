<?php

namespace App\Services\Chatbot\Tools\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GetPlatformAnalytics implements ChatbotToolInterface
{
    public function name(): string { return 'get_platform_analytics'; }

    public function description(): string
    {
        return 'Analitik performa platform Pusat Laundry dalam bahasa natural: jumlah toko baru, tren order, dan metrik operasional. Data komisi dan keuangan inti HANYA disertakan jika pemanggil memiliki role platform_owner (disembunyikan ketat untuk admin_owner).';
    }

    public function allowedRoles(): array { return ['platform_owner', 'admin_owner']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'periode' => [
                    'type' => 'string',
                    'description' => 'Periode analisis: "hari_ini", "minggu_ini", "bulan_ini", atau "semua"',
                    'enum' => ['hari_ini', 'minggu_ini', 'bulan_ini', 'semua'],
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $periode = $params['periode'] ?? 'minggu_ini';
        $now = Carbon::now();

        // 1. Toko Stats
        $tokoQuery = Toko::query();
        $orderQuery = Order::query();

        match ($periode) {
            'hari_ini' => [
                $tokoQuery->whereDate('created_at', $now->toDateString()),
                $orderQuery->whereDate('created_at', $now->toDateString()),
            ],
            'minggu_ini' => [
                $tokoQuery->where('created_at', '>=', $now->copy()->startOfWeek()),
                $orderQuery->where('created_at', '>=', $now->copy()->startOfWeek()),
            ],
            'bulan_ini' => [
                $tokoQuery->where('created_at', '>=', $now->copy()->startOfMonth()),
                $orderQuery->where('created_at', '>=', $now->copy()->startOfMonth()),
            ],
            default => null,
        };

        $tokoBaru = $tokoQuery->count();
        $totalTokoAktif = Toko::where('status', 'approved')->count();
        $tokoPending = Toko::where('status', 'submitted')->count();

        $orders = $orderQuery->get(['id', 'status', 'total_biaya']);
        $totalOrders = $orders->count();
        $ordersSelesai = $orders->filter(fn($o) => in_array($o->status instanceof OrderStatus ? $o->status->value : $o->status, ['selesai', 'cod_selesai']));

        $result = [
            'periode' => $periode,
            'operasional' => [
                'toko_baru_terdaftar' => $tokoBaru,
                'total_toko_aktif' => $totalTokoAktif,
                'toko_menunggu_approval' => $tokoPending,
                'total_order_masuk' => $totalOrders,
                'order_selesai' => $ordersSelesai->count(),
            ],
        ];

        // DEFENSE IN DEPTH: Data komisi & keuangan HANYA untuk PLATFORM_OWNER
        if ($activeRole === 'platform_owner') {
            $totalGmv = $ordersSelesai->sum('total_biaya');
            // Standar komisi 10%
            $komisiRate = 0.10;
            $komisiPlatform = $totalGmv * $komisiRate;

            $result['keuangan_platform'] = [
                'gmv_omzet_kotor' => 'Rp ' . number_format($totalGmv, 0, ',', '.'),
                'komisi_platform_estimasi' => 'Rp ' . number_format($komisiPlatform, 0, ',', '.') . ' (10%)',
                'payout_mitra_bersih' => 'Rp ' . number_format($totalGmv - $komisiPlatform, 0, ',', '.'),
            ];
        } else {
            // ADMIN OWNER: Sembunyikan informasi keuangan dan tegaskan batasan role
            $result['keuangan_platform'] = [
                'status' => 'RESTRICTED',
                'pesan' => 'Data komisi dan settlement keuangan platform hanya dapat diakses oleh Platform Owner.',
            ];
        }

        return $result;
    }
}
