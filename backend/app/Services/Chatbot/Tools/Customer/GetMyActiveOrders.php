<?php

namespace App\Services\Chatbot\Tools\Customer;

use App\Models\Order;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetMyActiveOrders implements ChatbotToolInterface
{
    public function name(): string { return 'get_my_active_orders'; }

    public function description(): string
    {
        return 'Mengambil daftar order aktif milik customer yang sedang login. Order aktif adalah yang belum berstatus "selesai" atau "dibatalkan".';
    }

    public function allowedRoles(): array { return ['customer']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $orders = Order::where('customer_id', $user->id)
            ->whereNotIn('status', ['selesai', 'dibatalkan'])
            ->with(['toko:id,nama,alamat', 'tarif:id,jenis_layanan,harga', 'kurir:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'toko_id', 'tarif_id', 'kurir_id', 'status', 'alamat_jemput', 'berat', 'total_biaya', 'created_at']);

        if ($orders->isEmpty()) {
            return ['message' => 'Kamu tidak punya order aktif saat ini.', 'orders' => []];
        }

        return [
            'message' => "Ditemukan {$orders->count()} order aktif.",
            'orders' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'toko' => $o->toko?->nama ?? '-',
                'layanan' => $o->tarif?->jenis_layanan ?? '-',
                'status' => $o->status instanceof \App\Enums\OrderStatus ? $o->status->label() : $o->status,
                'status_raw' => $o->status instanceof \App\Enums\OrderStatus ? $o->status->value : $o->status,
                'kurir' => $o->kurir?->name ?? 'Belum ditugaskan',
                'berat' => $o->berat,
                'total_biaya' => $o->total_biaya ? 'Rp ' . number_format($o->total_biaya, 0, ',', '.') : 'Belum ditimbang',
                'tanggal' => $o->created_at?->format('d M Y H:i'),
            ])->toArray(),
        ];
    }
}
