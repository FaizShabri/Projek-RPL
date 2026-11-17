<?php

namespace App\Services\Chatbot\Tools\Customer;

use App\Models\KurirLocationLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetKurirLocation implements ChatbotToolInterface
{
    public function name(): string { return 'get_kurir_location'; }

    public function description(): string
    {
        return 'Mengambil posisi GPS terakhir kurir untuk order aktif customer. Hanya tersedia saat status order "dijemput" atau "diantar". Hanya bisa melihat order milik sendiri.';
    }

    public function allowedRoles(): array { return ['customer']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_id' => [
                    'type' => 'integer',
                    'description' => 'ID order yang ingin dilacak kurirnya',
                ],
            ],
            'required' => ['order_id'],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $order = Order::where('id', $params['order_id'])
            ->where('customer_id', $user->id)
            ->first(['id', 'kurir_id', 'status']);

        if (!$order) {
            return ['error' => 'Order tidak ditemukan atau bukan milik kamu.'];
        }

        $statusValue = $order->status instanceof \App\Enums\OrderStatus ? $order->status->value : $order->status;
        $trackableStatuses = ['dijemput', 'diantar'];

        if (!in_array($statusValue, $trackableStatuses)) {
            return [
                'message' => 'Tracking kurir hanya tersedia saat status order "Pakaian Dijemput Kurir" atau "Kurir Sedang Mengantar".',
                'status_saat_ini' => $order->status instanceof \App\Enums\OrderStatus ? $order->status->label() : $order->status,
            ];
        }

        if (!$order->kurir_id) {
            return ['message' => 'Kurir belum ditugaskan untuk order ini.'];
        }

        $latestLocation = KurirLocationLog::where('kurir_id', $order->kurir_id)
            ->where('order_id', $order->id)
            ->orderBy('created_at', 'desc')
            ->first(['latitude', 'longitude', 'created_at']);

        if (!$latestLocation) {
            return ['message' => 'Lokasi kurir tidak tersedia saat ini. Kurir mungkin sedang offline atau GPS mati.'];
        }

        $ageSeconds = now()->diffInSeconds($latestLocation->created_at);

        return [
            'kurir_location' => [
                'latitude' => round($latestLocation->latitude, 4),
                'longitude' => round($latestLocation->longitude, 4),
                'terakhir_update' => $latestLocation->created_at?->format('H:i:s'),
                'usia_data_detik' => $ageSeconds,
                'status_gps' => $ageSeconds > 60 ? 'Mungkin offline (data >1 menit)' : 'Aktif',
            ],
        ];
    }
}
