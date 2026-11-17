<?php

namespace App\Services\Chatbot\Tools\Customer;

use App\Models\Tarif;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetTokoTarif implements ChatbotToolInterface
{
    public function name(): string { return 'get_toko_tarif'; }

    public function description(): string
    {
        return 'Mengambil daftar tarif/harga layanan dari suatu toko laundry. Hanya menampilkan tarif toko yang sudah approved.';
    }

    public function allowedRoles(): array { return ['customer']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'toko_id' => [
                    'type' => 'integer',
                    'description' => 'ID toko yang ingin dilihat tarifnya',
                ],
            ],
            'required' => ['toko_id'],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $toko = Toko::approved()->find($params['toko_id'], ['id', 'nama']);

        if (!$toko) {
            return ['error' => 'Toko tidak ditemukan atau belum approved.'];
        }

        $tarifs = Tarif::where('toko_id', $toko->id)
            ->get(['id', 'jenis_layanan', 'harga']);

        if ($tarifs->isEmpty()) {
            return ['message' => "Toko {$toko->nama} belum memiliki tarif layanan."];
        }

        return [
            'toko' => $toko->nama,
            'tarif' => $tarifs->map(fn ($t) => [
                'id' => $t->id,
                'jenis_layanan' => $t->jenis_layanan,
                'harga_per_kg' => 'Rp ' . number_format($t->harga, 0, ',', '.'),
            ])->toArray(),
        ];
    }
}
