<?php

namespace App\Services\Chatbot\Tools\OwnerToko;

use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;
use Illuminate\Support\Facades\DB;

class GetTarifComparison implements ChatbotToolInterface
{
    public function name(): string { return 'get_tarif_comparison'; }

    public function description(): string
    {
        return 'Memberikan saran & perbandingan tarif toko dengan rata-rata tarif toko lain di area/kota yang sama. Data toko lain ditampilkan dalam bentuk agregat rata-rata/min/max secara anonim tanpa membuka identitas toko lain.';
    }

    public function allowedRoles(): array { return ['owner_toko', 'admin_toko']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'jenis_layanan' => [
                    'type' => 'string',
                    'description' => 'Jenis layanan yang ingin dibandingkan (opsional, contoh: "Cuci Komplit", "Cuci Kering", "Setrika")',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $tokoQuery = Toko::query();
        if ($activeRole === 'owner_toko') {
            $tokoQuery->where('owner_id', $user->id);
        } else {
            $tokoQuery->whereHas('staff', fn($q) => $q->where('user_id', $user->id));
        }
        $myToko = $tokoQuery->first();

        if (!$myToko) {
            return ['error' => 'Toko Anda tidak ditemukan.'];
        }

        // Ambil tarif toko milik sendiri
        $myTarifs = Tarif::where('toko_id', $myToko->id)->get();

        // Hitung statistik agregat dari SEMUA toko approved lainnya (anonim, no toko names)
        $aggregateQuery = Tarif::join('tokos', 'tarifs.toko_id', '=', 'tokos.id')
            ->where('tokos.status', 'approved')
            ->where('tokos.id', '!=', $myToko->id);

        if (!empty($params['jenis_layanan'])) {
            $aggregateQuery->where('tarifs.jenis_layanan', 'like', '%' . $params['jenis_layanan'] . '%');
        }

        $marketStats = $aggregateQuery->select(
            'tarifs.jenis_layanan',
            DB::raw('COUNT(DISTINCT tarifs.toko_id) as total_toko_kompetitor'),
            DB::raw('AVG(tarifs.harga) as rata_rata_pasar'),
            DB::raw('MIN(tarifs.harga) as harga_terendah'),
            DB::raw('MAX(tarifs.harga) as harga_tertinggi')
        )->groupBy('tarifs.jenis_layanan')->get();

        $comparison = [];
        foreach ($myTarifs as $tarif) {
            $stat = $marketStats->firstWhere('jenis_layanan', $tarif->jenis_layanan);
            $avg = $stat ? (float)$stat->rata_rata_pasar : null;

            $statusHarga = 'Belum ada data pasar serupa';
            if ($avg) {
                $diffPercent = (($tarif->harga - $avg) / $avg) * 100;
                if ($diffPercent > 15) {
                    $statusHarga = 'Lebih tinggi ' . round(abs($diffPercent)) . '% dari rata-rata pasar (Premium)';
                } elseif ($diffPercent < -15) {
                    $statusHarga = 'Lebih rendah ' . round(abs($diffPercent)) . '% dari rata-rata pasar (Sangat Kompetitif)';
                } else {
                    $statusHarga = 'Sesuai dengan rata-rata pasar umum';
                }
            }

            $comparison[] = [
                'jenis_layanan' => $tarif->jenis_layanan,
                'harga_toko_anda' => 'Rp ' . number_format($tarif->harga, 0, ',', '.'),
                'rata_rata_pasar' => $avg ? 'Rp ' . number_format($avg, 0, ',', '.') : 'N/A',
                'rentang_pasar' => $stat ? ('Rp ' . number_format($stat->harga_terendah, 0, ',', '.') . ' - Rp ' . number_format($stat->harga_tertinggi, 0, ',', '.')) : 'N/A',
                'analisis' => $statusHarga,
            ];
        }

        return [
            'toko' => $myToko->nama,
            'perbandingan_tarif' => $comparison,
            'catatan' => 'Data kompetitor diagregasikan secara anonim demi menjaga kerahasiaan antar toko mitra.',
        ];
    }
}
