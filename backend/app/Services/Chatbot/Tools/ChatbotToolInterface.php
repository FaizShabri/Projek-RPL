<?php

namespace App\Services\Chatbot\Tools;

use App\Models\User;

/**
 * Interface untuk semua chatbot tool.
 * Setiap tool HARUS:
 * - Read-only (tidak boleh mengubah data)
 * - Memiliki daftar role yang diizinkan
 * - Mengembalikan deskripsi tool dalam format Anthropic function calling
 */
interface ChatbotToolInterface
{
    /**
     * Nama unik tool (digunakan sebagai function name oleh Claude).
     */
    public function name(): string;

    /**
     * Deskripsi tool (ditampilkan ke Claude agar tahu kapan harus memanggilnya).
     */
    public function description(): string;

    /**
     * Daftar role yang boleh menggunakan tool ini.
     * @return string[]
     */
    public function allowedRoles(): array;

    /**
     * Schema parameter input dalam format JSON Schema (untuk Anthropic tool use).
     */
    public function inputSchema(): array;

    /**
     * Eksekusi tool. HANYA READ-ONLY QUERY.
     *
     * @param array $params Parameter dari Claude
     * @param User $user User yang sedang login
     * @param string $activeRole Role aktif user saat ini
     * @return array Hasil yang akan dikembalikan ke Claude
     */
    public function execute(array $params, User $user, string $activeRole): array;
}
