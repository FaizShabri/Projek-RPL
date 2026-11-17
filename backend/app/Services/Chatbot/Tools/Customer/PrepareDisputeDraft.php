<?php

namespace App\Services\Chatbot\Tools\Customer;

use App\Models\Order;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class PrepareDisputeDraft implements ChatbotToolInterface
{
    public function name(): string { return 'prepare_dispute_draft'; }

    public function description(): string
    {
        return 'Membantu customer menyiapkan informasi untuk membuka dispute/komplain. Chatbot mengumpulkan info awal (order mana, masalah apa), lalu MENGARAHKAN ke form dispute resmi. TIDAK membuka dispute secara otomatis.';
    }

    public function allowedRoles(): array { return ['customer']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_id' => [
                    'type' => 'integer',
                    'description' => 'ID order yang ingin dibuatkan dispute',
                ],
                'kategori_masalah' => [
                    'type' => 'string',
                    'description' => 'Kategori masalah: "berat_tidak_sesuai", "barang_rusak", "barang_hilang", "keterlambatan", "lainnya"',
                    'enum' => ['berat_tidak_sesuai', 'barang_rusak', 'barang_hilang', 'keterlambatan', 'lainnya'],
                ],
                'deskripsi_masalah' => [
                    'type' => 'string',
                    'description' => 'Deskripsi singkat masalah yang dialami',
                ],
            ],
            'required' => ['order_id'],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        // Validasi order milik customer
        $order = Order::where('id', $params['order_id'])
            ->where('customer_id', $user->id)
            ->with(['toko:id,nama', 'disputes'])
            ->first(['id', 'toko_id', 'status', 'created_at']);

        if (!$order) {
            return ['error' => 'Order tidak ditemukan atau bukan milik kamu.'];
        }

        // Cek apakah sudah ada dispute aktif
        $activeDispute = $order->disputes->whereIn('status', ['open', 'in_review'])->first();
        if ($activeDispute) {
            return [
                'message' => 'Order ini sudah memiliki dispute aktif yang belum diselesaikan.',
                'dispute_status' => $activeDispute->status,
                'info' => 'Silakan tunggu hingga dispute yang ada diselesaikan oleh admin.',
            ];
        }

        return [
            'draft_dispute' => [
                'order_id' => $order->id,
                'toko' => $order->toko?->nama,
                'kategori' => $params['kategori_masalah'] ?? null,
                'deskripsi' => $params['deskripsi_masalah'] ?? null,
            ],
            'instruksi' => 'Untuk membuka dispute resmi, silakan buka halaman "Pesanan Saya", pilih order #' . $order->id . ', lalu klik tombol "Laporkan Masalah / Dispute". Isi form dispute dengan informasi di atas.',
            'peringatan' => 'Saya TIDAK bisa membuka dispute secara otomatis. Kamu perlu mengkonfirmasi dan mengirimkan dispute melalui form resmi di halaman pesanan.',
            'link_halaman' => '/customer/orders',
        ];
    }
}
