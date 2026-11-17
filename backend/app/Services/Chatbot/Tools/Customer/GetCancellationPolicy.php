<?php

namespace App\Services\Chatbot\Tools\Customer;

use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetCancellationPolicy implements ChatbotToolInterface
{
    public function name(): string { return 'get_cancellation_policy'; }

    public function description(): string
    {
        return 'Menampilkan kebijakan pembatalan order, cara order, dan FAQ umum Pusat Laundry. Gunakan tool ini ketika customer bertanya tentang cara pakai aplikasi, aturan cancel, atau pertanyaan umum lainnya.';
    }

    public function allowedRoles(): array { return ['customer']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topik' => [
                    'type' => 'string',
                    'description' => 'Topik FAQ: "cancel", "cara_order", "pembayaran", "estimasi", "umum"',
                    'enum' => ['cancel', 'cara_order', 'pembayaran', 'estimasi', 'umum'],
                ],
            ],
            'required' => ['topik'],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $topik = $params['topik'] ?? 'umum';

        $faq = match ($topik) {
            'cancel' => [
                'judul' => 'Kebijakan Pembatalan Order',
                'isi' => implode("\n", [
                    '• Order dapat dibatalkan oleh customer HANYA pada status:',
                    '  1. "Menunggu Konfirmasi Toko" — order baru dibuat, toko belum konfirmasi.',
                    '  2. "Menunggu Kurir" — toko sudah konfirmasi, tapi kurir belum jemput.',
                    '',
                    '• Setelah status "Dijemput" (pakaian sudah di tangan kurir), order TIDAK BISA dibatalkan oleh customer.',
                    '',
                    '• Untuk membatalkan, buka halaman "Pesanan Saya", pilih order, lalu klik tombol "Batalkan Pesanan".',
                    '',
                    '• Jika ada masalah setelah pakaian dijemput, gunakan fitur Dispute untuk melaporkan kendala.',
                ]),
            ],
            'cara_order' => [
                'judul' => 'Cara Order Laundry',
                'isi' => implode("\n", [
                    '1. Buka halaman Marketplace dan pilih toko laundry.',
                    '2. Lihat tarif layanan dan pilih jenis layanan yang diinginkan.',
                    '3. Masukkan alamat penjemputan dan catatan (jika ada).',
                    '4. Klik "Buat Pesanan" — status awal: "Menunggu Konfirmasi Toko".',
                    '5. Toko akan mengonfirmasi pesanan, lalu kurir ditugaskan.',
                    '6. Kurir jemput pakaian → bawa ke toko → ditimbang → dicuci → diantar kembali.',
                    '7. Pembayaran dilakukan secara COD (Cash on Delivery) saat kurir mengantar pakaian bersih.',
                ]),
            ],
            'pembayaran' => [
                'judul' => 'Metode Pembayaran',
                'isi' => implode("\n", [
                    '• Saat ini Pusat Laundry menggunakan sistem COD (Cash on Delivery).',
                    '• Kamu akan mendapat notifikasi jumlah biaya setelah pakaian ditimbang di toko.',
                    '• Siapkan uang tunai pas saat kurir mengantar pakaian bersih.',
                    '• Biaya dihitung berdasarkan: berat pakaian (kg) × tarif layanan per kg.',
                ]),
            ],
            'estimasi' => [
                'judul' => 'Estimasi Harga',
                'isi' => implode("\n", [
                    '• Harga dihitung berdasarkan berat aktual pakaian × tarif per kg dari toko.',
                    '• Berat ditimbang di toko setelah kurir mengantar pakaian ke sana.',
                    '• Tarif berbeda-beda per toko dan jenis layanan (cuci kering, cuci basah, setrika, dll).',
                    '• Untuk estimasi, cek tarif toko di halaman detail toko atau tanyakan ke saya tentang tarif toko tertentu.',
                ]),
            ],
            default => [
                'judul' => 'FAQ Umum Pusat Laundry',
                'isi' => implode("\n", [
                    '• Pusat Laundry adalah marketplace laundry yang menghubungkan customer dengan toko laundry terdekat.',
                    '• Kami menyediakan layanan penjemputan dan pengantaran via kurir.',
                    '• Pembayaran menggunakan sistem COD (bayar tunai saat pakaian diantar).',
                    '• Kamu bisa memberikan review dan rating setelah order selesai.',
                    '• Jika ada masalah, gunakan fitur Dispute untuk melaporkan kendala.',
                    '',
                    'Topik FAQ tersedia: cancel, cara_order, pembayaran, estimasi.',
                ]),
            ],
        };

        return $faq;
    }
}
