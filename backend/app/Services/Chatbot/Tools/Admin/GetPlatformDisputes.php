<?php

namespace App\Services\Chatbot\Tools\Admin;

use App\Models\Dispute;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetPlatformDisputes implements ChatbotToolInterface
{
    public function name(): string { return 'get_platform_disputes'; }

    public function description(): string
    {
        return 'Mengambil ringkasan dispute di seluruh platform yang perlu diputuskan atau ditinjau oleh Admin/Platform Owner. Read-only.';
    }

    public function allowedRoles(): array { return ['platform_owner', 'admin_owner']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'description' => 'Status dispute: "open", "in_review", "resolved", "rejected", atau "butuh_tindakan"',
                    'enum' => ['open', 'in_review', 'resolved', 'rejected', 'butuh_tindakan'],
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $status = $params['status'] ?? 'butuh_tindakan';
        $query = Dispute::with([
            'order:id,customer_id,toko_id,total_biaya',
            'order.toko:id,nama',
            'order.customer:id,name',
            'pelapor:id,name'
        ])->orderBy('created_at', 'desc');

        if ($status === 'butuh_tindakan') {
            $query->whereIn('status', ['open', 'in_review']);
        } elseif (!empty($status)) {
            $query->where('status', $status);
        }

        $disputes = $query->limit(15)->get();

        return [
            'total_ditemukan' => $disputes->count(),
            'filter_status' => $status,
            'disputes' => $disputes->map(fn($d) => [
                'id' => $d->id,
                'order_id' => $d->order_id,
                'toko' => $d->order?->toko?->nama ?? 'Toko',
                'customer' => $d->order?->customer?->name ?? 'Customer',
                'pelapor' => $d->pelapor?->name ?? 'User',
                'alasan' => $d->alasan,
                'deskripsi' => $d->deskripsi,
                'status' => $d->status,
                'resolusi' => $d->resolusi ?? 'Belum ada',
                'tanggal' => $d->created_at?->format('d M Y H:i'),
            ])->toArray(),
            'instruksi' => 'Untuk menyelesaikan dispute (resolve/reject), admin wajib membukanya di menu Dashboard Sengketa dan mengisi catatan resolusi resmi.',
        ];
    }
}
