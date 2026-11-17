<?php

namespace App\Services\Chatbot\Tools\Customer;

use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;
use Illuminate\Support\Facades\DB;

class SearchTokoLaundry implements ChatbotToolInterface
{
    public function name(): string { return 'search_toko_laundry'; }

    public function description(): string
    {
        return 'Mencari toko laundry yang sudah approved di marketplace. Bisa filter berdasarkan nama atau menampilkan yang terdekat berdasarkan koordinat customer. Menampilkan rating rata-rata dan jumlah review.';
    }

    public function allowedRoles(): array { return ['customer']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'nama' => [
                    'type' => 'string',
                    'description' => 'Kata kunci pencarian nama toko (opsional)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Jumlah hasil maksimal (default 5, max 10)',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $query = Toko::approved()
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if (!empty($params['nama'])) {
            $query->where('nama', 'like', '%' . $params['nama'] . '%');
        }

        $limit = min($params['limit'] ?? 5, 10);

        $tokos = $query->orderByDesc('reviews_avg_rating')
            ->limit($limit)
            ->get(['id', 'nama', 'alamat']);

        if ($tokos->isEmpty()) {
            return ['message' => 'Tidak ditemukan toko laundry yang cocok.', 'tokos' => []];
        }

        return [
            'message' => "Ditemukan {$tokos->count()} toko laundry.",
            'tokos' => $tokos->map(fn ($t) => [
                'id' => $t->id,
                'nama' => $t->nama,
                'alamat' => $t->alamat,
                'rating_rata_rata' => $t->reviews_avg_rating ? round($t->reviews_avg_rating, 1) : 'Belum ada review',
                'jumlah_review' => $t->reviews_count,
            ])->toArray(),
            'info' => 'Untuk melihat tarif detail, tanyakan tentang tarif toko tertentu.',
        ];
    }
}
