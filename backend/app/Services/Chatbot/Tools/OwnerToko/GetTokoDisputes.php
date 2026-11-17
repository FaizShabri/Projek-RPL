<?php

namespace App\Services\Chatbot\Tools\OwnerToko;

use App\Models\Dispute;
use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class GetTokoDisputes implements ChatbotToolInterface
{
    public function name(): string { return 'get_toko_disputes'; }

    public function description(): string
    {
        return 'Mengambil daftar dan ringkasan sengketa/dispute terkait toko milik owner yang login. Menampilkan status komplain, alasan masalah, dan apakah memerlukan respon toko.';
    }

    public function allowedRoles(): array { return ['owner_toko', 'admin_toko']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter status dispute: "open", "in_review", "resolved", "rejected", atau "aktif"',
                    'enum' => ['open', 'in_review', 'resolved', 'rejected', 'aktif'],
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

        $query = Dispute::whereHas('order', function($q) use ($myToko) {
            $q->where('toko_id', $myToko->id);
        })->with(['order:id,total_biaya,berat,customer_id', 'order.customer:id,name', 'pelapor:id,name']);

        $statusFilter = $params['status'] ?? 'aktif';
        if ($statusFilter === 'aktif') {
            $query->whereIn('status', ['open', 'in_review']);
        } elseif (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        $disputes = $query->orderBy('created_at', 'desc')->get();

        return [
            'toko' => $myToko->nama,
            'total' => $disputes->count(),
            'disputes' => $disputes->map(fn($d) => [
                'id' => $d->id,
                'order_id' => $d->order_id,
                'customer' => $d->order?->customer?->name ?? 'Customer',
                'pelapor' => $d->pelapor?->name ?? 'User',
                'alasan' => $d->alasan,
                'deskripsi' => $d->deskripsi,
                'status' => $d->status,
                'resolusi' => $d->resolusi ?? 'Belum ada keputusan platform',
                'tanggal' => $d->created_at?->format('d M Y H:i'),
            ])->toArray(),
        ];
    }
}
