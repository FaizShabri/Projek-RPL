<?php

namespace App\Services\Chatbot\Tools\OwnerToko;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;
use Carbon\Carbon;

class GetTokoPerformance implements ChatbotToolInterface
{
    public function name(): string { return 'get_toko_performance'; }

    public function description(): string
    {
        return 'Mengambil ringkasan performa toko milik owner yang sedang login: omzet harian/mingguan/bulanan, jumlah order per status, dan rating rata-rata toko. Scoped HANYA ke toko milik owner yang login.';
    }

    public function allowedRoles(): array { return ['owner_toko', 'admin_toko']; }

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
                'toko_id' => [
                    'type' => 'integer',
                    'description' => 'ID toko spesifik (opsional jika owner hanya punya 1 toko)',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        // Temukan toko milik user (owner_toko) atau toko staff (admin_toko)
        $tokoQuery = Toko::query();
        if ($activeRole === 'owner_toko') {
            $tokoQuery->where('owner_id', $user->id);
        } else {
            $tokoQuery->whereHas('staff', fn($q) => $q->where('user_id', $user->id));
        }

        if (!empty($params['toko_id'])) {
            $tokoQuery->where('id', $params['toko_id']);
        }

        $toko = $tokoQuery->first();

        if (!$toko) {
            return ['error' => 'Toko tidak ditemukan atau Anda tidak memiliki akses ke toko tersebut.'];
        }

        $periode = $params['periode'] ?? 'minggu_ini';
        $orderQuery = Order::where('toko_id', $toko->id);

        $now = Carbon::now();
        match ($periode) {
            'hari_ini' => $orderQuery->whereDate('created_at', $now->toDateString()),
            'minggu_ini' => $orderQuery->where('created_at', '>=', $now->copy()->startOfWeek()),
            'bulan_ini' => $orderQuery->where('created_at', '>=', $now->copy()->startOfMonth()),
            default => null,
        };

        $orders = $orderQuery->get(['id', 'status', 'total_biaya', 'berat', 'created_at']);

        $totalOrder = $orders->count();
        $orderSelesai = $orders->filter(fn($o) => in_array($o->status instanceof OrderStatus ? $o->status->value : $o->status, ['selesai', 'cod_selesai']));
        $omzet = $orderSelesai->sum('total_biaya');
        $totalBerat = $orders->sum('berat');
        $orderAktif = $orders->filter(fn($o) => !in_array($o->status instanceof OrderStatus ? $o->status->value : $o->status, ['selesai', 'dibatalkan']))->count();

        // Rating rata-rata toko
        $toko->loadAvg('reviews', 'rating');
        $toko->loadCount('reviews');

        return [
            'toko' => [
                'id' => $toko->id,
                'nama' => $toko->nama,
            ],
            'periode' => $periode,
            'ringkasan' => [
                'total_order' => $totalOrder,
                'order_aktif_berjalan' => $orderAktif,
                'order_selesai' => $orderSelesai->count(),
                'total_omzet' => 'Rp ' . number_format($omzet, 0, ',', '.'),
                'total_berat_kg' => round($totalBerat, 1),
                'rating_rata_rata' => $toko->reviews_avg_rating ? round($toko->reviews_avg_rating, 2) : 'Belum ada rating',
                'total_ulasan' => $toko->reviews_count,
            ],
        ];
    }
}
