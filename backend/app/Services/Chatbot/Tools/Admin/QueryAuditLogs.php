<?php

namespace App\Services\Chatbot\Tools\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class QueryAuditLogs implements ChatbotToolInterface
{
    public function name(): string { return 'query_audit_logs'; }

    public function description(): string
    {
        return 'Mencari dan membaca riwayat audit log platform via parameter terstruktur (misal: "siapa yang approve toko X", "aksi admin 2 hari terakhir"). HANYA READ-ONLY dari tabel audit_logs.';
    }

    public function allowedRoles(): array { return ['platform_owner', 'admin_owner']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'Aksi spesifik (contoh: "APPROVE_TOKO", "REJECT_TOKO", "RESOLVE_DISPUTE", "TOGGLE_USER_STATUS", dll)',
                ],
                'target_type' => [
                    'type' => 'string',
                    'description' => 'Tipe target log (contoh: "toko", "dispute", "user", "order")',
                ],
                'target_id' => [
                    'type' => 'integer',
                    'description' => 'ID target spesifik',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maksimal jumlah log yang dikembalikan (default 10, max 20)',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $query = AuditLog::with('actor:id,name,email')
            ->orderBy('created_at', 'desc');

        if (!empty($params['action'])) {
            $query->where('action', 'like', '%' . $params['action'] . '%');
        }

        if (!empty($params['target_type'])) {
            $query->where('target_type', 'like', '%' . $params['target_type'] . '%');
        }

        if (!empty($params['target_id'])) {
            $query->where('target_id', $params['target_id']);
        }

        $limit = min($params['limit'] ?? 10, 20);
        $logs = $query->limit($limit)->get();

        return [
            'total_ditemukan' => $logs->count(),
            'logs' => $logs->map(fn($l) => [
                'id' => $l->id,
                'pelaku' => $l->actor ? "{$l->actor->name} ({$l->actor->email})" : 'Sistem / Anonim',
                'aksi' => $l->action,
                'target' => "{$l->target_type} #{$l->target_id}",
                'sebelum' => $l->before_state,
                'sesudah' => $l->after_state,
                'waktu' => $l->created_at?->format('d M Y H:i:s'),
            ])->toArray(),
        ];
    }
}
