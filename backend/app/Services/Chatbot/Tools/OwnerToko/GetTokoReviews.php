<?php

namespace App\Services\Chatbot\Tools\OwnerToko;

use App\Models\Review;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetTokoReviews implements ChatbotToolInterface
{
    public function name(): string { return 'get_toko_reviews'; }

    public function description(): string
    {
        return 'Mengambil ulasan dan rating customer terbaru untuk toko milik owner yang login. Termasuk ulasan yang memiliki rating rendah atau komplain.';
    }

    public function allowedRoles(): array { return ['owner_toko', 'admin_toko']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'rating_filter' => [
                    'type' => 'integer',
                    'description' => 'Filter berdasarkan bintang tertentu (1-5, opsional)',
                ],
                'hanya_komentar' => [
                    'type' => 'boolean',
                    'description' => 'Hanya tampilkan ulasan yang menyertakan komentar teks',
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

        $query = Review::where('toko_id', $myToko->id)
            ->with(['order:id,customer_id', 'order.customer:id,name'])
            ->orderBy('created_at', 'desc');

        if (!empty($params['rating_filter'])) {
            $query->where('rating', $params['rating_filter']);
        }

        if (!empty($params['hanya_komentar'])) {
            $query->whereNotNull('komentar')->where('komentar', '!=', '');
        }

        $reviews = $query->limit(10)->get();

        return [
            'toko' => $myToko->nama,
            'total_ditemukan' => $reviews->count(),
            'reviews' => $reviews->map(fn($r) => [
                'id' => $r->id,
                'order_id' => $r->order_id,
                'customer_nama' => $r->order?->customer?->name ?? 'Customer',
                'rating' => $r->rating,
                'komentar' => $r->komentar ?? '(Tanpa komentar)',
                'tanggal' => $r->created_at?->format('d M Y H:i'),
            ])->toArray(),
        ];
    }
}
