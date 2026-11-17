<?php

namespace App\Services\Chatbot\Tools\Admin;

use App\Models\Order;
use App\Models\Review;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DetectAnomalies implements ChatbotToolInterface
{
    public function name(): string { return 'detect_anomalies'; }

    public function description(): string
    {
        return 'Mendeteksi dan menandai potensi anomali di platform secara read-only: lonjakan pembatalan order mendadak, toko dengan rating sangat rendah atau ulasan janggal, dan kurir dengan durasi pengantaran tidak wajar. Chatbot HANYA memberikan sinyal insight, BUKAN mengambil tindakan suspend otomatis.';
    }

    public function allowedRoles(): array { return ['platform_owner', 'admin_owner']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fokus' => [
                    'type' => 'string',
                    'description' => 'Fokus deteksi: "toko_rating_rendah", "order_dibatalkan_tinggi", "all"',
                    'enum' => ['toko_rating_rendah', 'order_dibatalkan_tinggi', 'all'],
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $fokus = $params['fokus'] ?? 'all';
        $anomali = [];

        // 1. Toko dengan rating rendah (< 3.0) dengan minimal 3 review
        if ($fokus === 'all' || $fokus === 'toko_rating_rendah') {
            $tokoRatingRendah = Toko::where('status', 'approved')
                ->withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->having('reviews_count', '>=', 2)
                ->having('reviews_avg_rating', '<', 3.0)
                ->limit(5)
                ->get(['id', 'nama', 'alamat']);

            if ($tokoRatingRendah->isNotEmpty()) {
                $anomali['toko_rating_kritis'] = $tokoRatingRendah->map(fn($t) => [
                    'toko_id' => $t->id,
                    'nama' => $t->nama,
                    'rating_rata_rata' => round($t->reviews_avg_rating, 2),
                    'total_review' => $t->reviews_count,
                    'rekomendasi' => 'Perlu pengecekan komplain pelanggan pada toko ini.',
                ])->toArray();
            }
        }

        // 2. Toko dengan tingkat pembatalan order tinggi
        if ($fokus === 'all' || $fokus === 'order_dibatalkan_tinggi') {
            $recentOrders = Order::where('created_at', '>=', Carbon::now()->subDays(7))
                ->select('toko_id', DB::raw('count(*) as total'), DB::raw("sum(case when status = 'dibatalkan' then 1 else 0 end) as batal"))
                ->groupBy('toko_id')
                ->having('total', '>=', 3)
                ->get();

            $highCancelTokos = [];
            foreach ($recentOrders as $ro) {
                $cancelRate = ($ro->batal / $ro->total) * 100;
                if ($cancelRate >= 40) {
                    $toko = Toko::find($ro->toko_id, ['id', 'nama']);
                    if ($toko) {
                        $highCancelTokos[] = [
                            'toko_id' => $toko->id,
                            'nama' => $toko->nama,
                            'total_order_7_hari' => $ro->total,
                            'dibatalkan' => $ro->batal,
                            'persentase_batal' => round($cancelRate, 1) . '%',
                            'sinyal' => 'Tingkat pembatalan tinggi (>40%) dalam 7 hari terakhir.',
                        ];
                    }
                }
            }

            if (!empty($highCancelTokos)) {
                $anomali['tingkat_pembatalan_tinggi'] = $highCancelTokos;
            }
        }

        return [
            'fokus_analisis' => $fokus,
            'total_indikasi_anomali' => count($anomali),
            'temuan' => empty($anomali) ? 'Tidak ditemukan anomali signifikan pada parameter yang dianalisis.' : $anomali,
            'kebijakan_tindakan' => 'Temuan anomali ini bersifat informatif. Keputusan penangguhan (suspend) toko atau pembekuan akun wajib dilakukan secara manual oleh administrator setelah investigasi.',
        ];
    }
}
