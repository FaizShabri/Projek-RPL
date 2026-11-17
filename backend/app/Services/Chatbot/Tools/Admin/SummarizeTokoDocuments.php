<?php

namespace App\Services\Chatbot\Tools\Admin;

use App\Models\Toko;
use App\Models\User;
use App\Services\Chatbot\Tools\ChatbotToolInterface;

class SummarizeTokoDocuments implements ChatbotToolInterface
{
    public function name(): string { return 'summarize_toko_documents'; }

    public function description(): string
    {
        return 'Membantu merangkum dan mengekstrak poin penting dari dokumen legalitas toko yang sedang dalam proses review approval. Chatbot HANYA merangkum isi, BUKAN memutuskan approve/reject.';
    }

    public function allowedRoles(): array { return ['platform_owner', 'admin_owner']; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'toko_id' => [
                    'type' => 'integer',
                    'description' => 'ID toko yang ingin ditinjau dokumen legalitasnya',
                ],
            ],
            'required' => ['toko_id'],
        ];
    }

    public function execute(array $params, User $user, string $activeRole): array
    {
        $toko = Toko::with('owner:id,name,email')->find($params['toko_id']);

        if (!$toko) {
            return ['error' => 'Toko tidak ditemukan.'];
        }

        $dokumen = $toko->dokumen_legalitas;
        $hasDoc = !empty($dokumen);

        return [
            'toko' => [
                'id' => $toko->id,
                'nama' => $toko->nama,
                'owner' => $toko->owner?->name ?? 'N/A',
                'alamat' => $toko->alamat,
                'status_saat_ini' => $toko->status,
            ],
            'dokumen' => [
                'tersedia' => $hasDoc,
                'file_path' => $dokumen,
                'ekstensi' => $hasDoc ? pathinfo($dokumen, PATHINFO_EXTENSION) : null,
                'catatan_kelengkapan' => $hasDoc ? 'Dokumen legalitas telah diunggah oleh mitra pemilik toko.' : 'Belum mengunggah dokumen legalitas.',
            ],
            'poin_evaluasi_verifikator' => [
                '1. Pastikan nama usaha pada dokumen sesuai dengan nama toko pendaftar.',
                '2. Cek kejelasan alamat fisik operasional toko sesuai koordinat.',
                '3. Periksa masa berlaku izin usaha (NIB / SIUP).',
                '4. Pastikan nomor kontak dan identitas pemilik dapat dihubungi.',
            ],
            'peringatan' => 'AI Chatbot HANYA membantu merangkum data dan tidak memiliki otorisasi untuk melakukan approve atau reject. Keputusan approval WAJIB dilakukan manual oleh Admin di menu Moderasi Toko.',
        ];
    }
}
