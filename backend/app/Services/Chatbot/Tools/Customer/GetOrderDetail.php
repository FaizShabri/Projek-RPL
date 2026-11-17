<?php

namespace App\Services\Chatbot\Tools\Customer;

use App\Models\Order;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetOrderDetail implements ChatbotToolInterface
{
    public function name(): string { return 'get_order_detail'; }

    public function description(): string
    {
        return 'Mengambil detail lengkap satu order milik customer yang login, termasuk riwayat status. Hanya bisa melihat order milik sendiri.';
    }

    public function allowedRoles(): array { return ['customer']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_id' => [
                    'type' => 'integer',
                    'description' => 'ID order yang ingin dilihat detailnya',
                ],
            ],
            'required' => ['order_id'],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $order = Order::where('id', $params['order_id'])
            ->where('customer_id', $user->id) // SCOPED: hanya order milik customer ini
            ->with(['toko:id,nama,alamat', 'tarif:id,jenis_layanan,harga', 'kurir:id,name', 'statusHistories'])
            ->first();

        if (!$order) {
            return ['error' => 'Order tidak ditemukan atau bukan milik kamu.'];
        }

        return [
            'order' => [
                'id' => $order->id,
                'toko' => $order->toko?->nama,
                'alamat_toko' => $order->toko?->alamat,
                'layanan' => $order->tarif?->jenis_layanan,
                'harga_per_kg' => $order->tarif ? 'Rp ' . number_format($order->tarif->harga, 0, ',', '.') : '-',
                'status' => $order->status instanceof \App\Enums\OrderStatus ? $order->status->label() : $order->status,
                'status_raw' => $order->status instanceof \App\Enums\OrderStatus ? $order->status->value : $order->status,
                'kurir' => $order->kurir?->name ?? 'Belum ditugaskan',
                'alamat_jemput' => $order->alamat_jemput,
                'berat' => $order->berat ? "{$order->berat} kg" : 'Belum ditimbang',
                'total_biaya' => $order->total_biaya ? 'Rp ' . number_format($order->total_biaya, 0, ',', '.') : 'Belum dihitung',
                'dapat_dibatalkan' => $order->canCancelByCustomer(),
                'catatan' => $order->catatan,
                'tanggal_order' => $order->created_at?->format('d M Y H:i'),
                'riwayat_status' => $order->statusHistories->map(fn ($h) => [
                    'status' => $h->status instanceof \App\Enums\OrderStatus ? $h->status->label() : $h->status,
                    'keterangan' => $h->keterangan,
                    'waktu' => $h->created_at?->format('d M Y H:i'),
                ])->toArray(),
            ],
        ];
    }
}
