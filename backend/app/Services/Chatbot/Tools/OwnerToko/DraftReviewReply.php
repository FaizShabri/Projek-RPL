<?php

namespace App\Services\Chatbot\Tools\OwnerToko;

use App\Models\Review;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class DraftReviewReply implements ChatbotToolInterface
{
    public function name(): string { return 'draft_review_reply'; }

    public function description(): string
    {
        return 'Membantu membuatkan draft teks balasan sopan dan profesional untuk ulasan customer. TIDAK mengirim balasan secara otomatis — owner harus menyalin/mengirim secara manual melalui sistem.';
    }

    public function allowedRoles(): array { return ['owner_toko', 'admin_toko']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'review_id' => [
                    'type' => 'integer',
                    'description' => 'ID ulasan customer yang ingin dibalas',
                ],
                'nada_bicara' => [
                    'type' => 'string',
                    'description' => 'Gaya balasan: "ramah", "formal", "apologetic" (jika rating buruk), atau "singkat"',
                    'enum' => ['ramah', 'formal', 'apologetic', 'singkat'],
                ],
                'poin_tambahan' => [
                    'type' => 'string',
                    'description' => 'Poin spesifik yang ingin dimasukkan dalam balasan (contoh: kompensasi voucher, perbaikan mesin, dll)',
                ],
            ],
            'required' => ['review_id'],
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

        $review = Review::where('id', $params['review_id'])
            ->where('toko_id', $myToko->id)
            ->with(['order.customer:id,name'])
            ->first();

        if (!$review) {
            return ['error' => 'Ulasan tidak ditemukan pada toko Anda.'];
        }

        $customerName = $review->order?->customer?->name ?? 'Pelanggan';
        $rating = $review->rating;
        $komentar = $review->komentar ?? '';

        $tone = $params['nada_bicara'] ?? ($rating <= 3 ? 'apologetic' : 'ramah');
        $extra = $params['poin_tambahan'] ?? '';

        // Template draft respon
        $draft = match ($tone) {
            'apologetic' => "Halo Kak {$customerName}, terima kasih banyak atas masukannya untuk {$myToko->nama}. Kami memohon maaf yang sebesar-besarnya atas ketidaknyamanan yang Kakak alami terkait: \"{$komentar}\". Masukan ini menjadi bahan evaluasi serius bagi tim kami. {$extra} Kami berharap dapat memberikan pelayanan yang jauh lebih baik pada pesanan Kakak berikutnya. Salam hangat, Tim {$myToko->nama}.",
            'formal' => "Yth. Kak {$customerName}, terima kasih telah mempercayakan pakaian Anda kepada {$myToko->nama}. Kami sangat mengapresiasi ulasan yang diberikan. {$extra} Kami senantiasa berkomitmen untuk memberikan kualitas cuci terbaik bagi seluruh pelanggan kami. Hormat kami, Manajemen {$myToko->nama}.",
            'singkat' => "Terima kasih banyak Kak {$customerName} sudah laundry di {$myToko->nama}! Ditunggu cucian berikutnya ya Kak. {$extra}",
            default => "Halo Kak {$customerName}! Terima kasih banyak atas ulasan bintang {$rating}-nya untuk {$myToko->nama}! Senang sekali bisa membantu merawat pakaian Kakak hingga bersih dan wangi. {$extra} Ditunggu pesanan berikutnya ya Kak!",
        };

        return [
            'review_id' => $review->id,
            'customer' => $customerName,
            'rating' => $rating,
            'komentar_customer' => $komentar,
            'draft_balasan' => $draft,
            'instruksi' => 'Draft balasan di atas siap Anda salin dan gunakan. Chatbot TIDAK memposting balasan ini secara otomatis ke publik.',
        ];
    }
}
