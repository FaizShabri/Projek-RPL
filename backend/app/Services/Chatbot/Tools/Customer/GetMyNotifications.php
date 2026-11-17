<?php

namespace App\Services\Chatbot\Tools\Customer;

use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetMyNotifications implements ChatbotToolInterface
{
    public function name(): string
    {
        return 'get_my_notifications';
    }

    public function description(): string
    {
        return 'Membaca ringkasan notifikasi terbaru milik pengguna yang sedang login (read-only) untuk menjawab pertanyaan seperti "ada update apa di order saya?".';
    }

    public function allowedRoles(): array
    {
        return ['customer', 'kurir', 'owner_toko', 'admin_toko', 'admin_owner', 'platform_owner'];
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'unread_only' => [
                    'type' => 'boolean',
                    'description' => 'Jika true, hanya tampilkan notifikasi yang belum dibaca.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Jumlah notifikasi yang diambil (default 5, maksimal 10).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $limit = min((int) ($params['limit'] ?? 5), 10);
        $query = $user->notifications()->latest();

        if (!empty($params['unread_only'])) {
            $query = $user->unreadNotifications()->latest();
        }

        $notifications = $query->limit($limit)->get();

        if ($notifications->isEmpty()) {
            return [
                'message' => 'Tidak ada notifikasi baru untuk Anda saat ini.',
                'notifications' => [],
            ];
        }

        return [
            'total_unread' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(function ($notif) {
                $data = $notif->data ?? [];
                return [
                    'id' => $notif->id,
                    'type' => $data['type'] ?? 'info',
                    'title' => $data['title'] ?? 'Pemberitahuan',
                    'message' => $data['message'] ?? '',
                    'order_id' => $data['order_id'] ?? null,
                    'read' => !is_null($notif->read_at),
                    'time' => $notif->created_at?->diffForHumans() ?? '',
                ];
            })->all(),
        ];
    }
}
