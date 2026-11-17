<?php

namespace App\Services\Chatbot;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicService
{
    protected ?string $apiKey;
    protected string $model;
    protected int $maxTokens;
    protected string $apiUrl;
    protected ChatbotToolRegistry $toolRegistry;

    public function __construct(ChatbotToolRegistry $toolRegistry)
    {
        $this->toolRegistry = $toolRegistry;
        $this->apiKey = config('anthropic.api_key');
        $this->model = config('anthropic.model', 'claude-3-5-sonnet-20241022');
        $this->maxTokens = (int) config('anthropic.max_tokens', 2048);
        $this->apiUrl = config('anthropic.api_url', 'https://api.anthropic.com/v1/messages');
    }

    /**
     * Memproses pesan chat dengan system prompt dan tools yang di-scope per role.
     * Mendukung multi-turn tool calling loop secara read-only.
     */
    public function chat(string $userMessage, array $conversationHistory, User $user, string $activeRole): array
    {
        $systemPrompt = $this->buildSystemPrompt($user, $activeRole);
        $toolsSchema = $this->toolRegistry->getAnthropicToolsSchema($activeRole);

        // Jika API Key tidak ada:
        if (empty($this->apiKey)) {
            // Mode fallback HANYA diizinkan di environment local atau testing
            if (!app()->environment('local', 'testing') || config('app.env') === 'production') {
                Log::critical('Anthropic API key belum dikonfigurasi pada environment non-lokal/production.', [
                    'env' => config('app.env'),
                    'user_id' => $user->id,
                ]);
                throw new RuntimeException('Layanan asisten AI sedang dalam pemeliharaan konfigurasi. Silakan hubungi administrator.');
            }

            // Jalankan mode demo lokal/testing dengan authorization check & formatter rapi
            return $this->handleLocalOrTestChat($userMessage, $user, $activeRole, $toolsSchema);
        }

        // Sanitasi urutan riwayat pesan: pesan pertama WAJIB user & harus strictly alternating
        $messages = $this->sanitizeMessages($conversationHistory, $userMessage);

        $toolsUsed = [];
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $accumulatedText = '';

        // Multi-turn loop untuk tool calling (maks 5 turn untuk keamanan dan pencegahan infinite loop)
        for ($i = 0; $i < 5; $i++) {
            $payload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $systemPrompt,
                'messages' => $messages,
            ];

            if (!empty($toolsSchema)) {
                $payload['tools'] = array_values($toolsSchema);
            }

            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])->timeout(25)->post($this->apiUrl, $payload);
            } catch (\Throwable $e) {
                Log::error('Anthropic API Connection Exception', [
                    'error' => $e->getMessage(),
                    'turn' => $i,
                    'user_id' => $user->id,
                ]);
                throw new RuntimeException('Gagal menghubungi server asisten AI. Silakan coba sesaat lagi.');
            }

            if (!$response->successful()) {
                Log::error('Anthropic API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'turn' => $i,
                ]);
                throw new RuntimeException('Gagal berkomunikasi dengan Anthropic Claude API (Status ' . $response->status() . ').');
            }

            $data = $response->json();
            $totalInputTokens += $data['usage']['input_tokens'] ?? 0;
            $totalOutputTokens += $data['usage']['output_tokens'] ?? 0;
            $stopReason = $data['stop_reason'] ?? 'end_turn';

            $contentBlocks = $data['content'] ?? [];
            $toolCalls = [];
            $turnText = '';

            foreach ($contentBlocks as $block) {
                if ($block['type'] === 'text') {
                    $turnText .= $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $toolCalls[] = $block;
                }
            }

            if ($turnText !== '') {
                $accumulatedText = $accumulatedText !== '' ? ($accumulatedText . "\n\n" . $turnText) : $turnText;
            }

            // Peringatan jika response terpotong max_tokens
            if ($stopReason === 'max_tokens') {
                Log::warning('Anthropic API response truncated due to max_tokens limit.', [
                    'max_tokens' => $this->maxTokens,
                    'turn' => $i,
                ]);
            }

            // Jika tidak ada tool call atau stop_reason bukan 'tool_use', asisten selesai menjawab
            if (empty($toolCalls) || $stopReason !== 'tool_use') {
                return [
                    'reply' => $accumulatedText ?: 'Permintaan Anda telah diproses.',
                    'tools_used' => array_values(array_unique($toolsUsed)),
                    'token_usage' => [
                        'input_tokens' => $totalInputTokens,
                        'output_tokens' => $totalOutputTokens,
                    ],
                    'is_demo' => false,
                ];
            }

            // Tambahkan pesan asisten yang berisi tool use ke conversation
            $messages[] = [
                'role' => 'assistant',
                'content' => $contentBlocks,
            ];

            // Eksekusi tool satu per satu secara read-only
            $toolResultBlocks = [];
            foreach ($toolCalls as $call) {
                $toolName = $call['name'];
                $toolInput = $call['input'] ?? [];
                $toolsUsed[] = $toolName;

                try {
                    $result = $this->toolRegistry->executeTool($toolName, $toolInput, $user, $activeRole);
                } catch (\Throwable $e) {
                    Log::warning('Tool execution failed in chatbot loop', [
                        'tool' => $toolName,
                        'error' => $e->getMessage(),
                    ]);
                    $result = ['error' => $e->getMessage()];
                }

                $toolResultBlocks[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $call['id'],
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }

            // Kirim kembali hasil eksekusi tool ke Claude
            $messages[] = [
                'role' => 'user',
                'content' => $toolResultBlocks,
            ];
        }

        return [
            'reply' => $accumulatedText ?: 'Permintaan Anda telah selesai diproses.',
            'tools_used' => array_values(array_unique($toolsUsed)),
            'token_usage' => [
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
            ],
            'is_demo' => false,
        ];
    }

    /**
     * Sanitasi riwayat pesan percakapan:
     * 1. Buang pesan sambutan 'assistant' di awal (API Claude mewajibkan pesan pertama ber-role 'user').
     * 2. Gabungkan pesan dengan role berturut-turut yang sama sehingga struktur 'user' dan 'assistant' bergantian ketat.
     * 3. Pastikan pesan terkini dari user berada di urutan paling akhir.
     */
    public function sanitizeMessages(array $conversationHistory, string $currentMessage): array
    {
        $cleaned = [];

        foreach ($conversationHistory as $msg) {
            if (!isset($msg['role'], $msg['content']) || !is_string($msg['content']) || trim($msg['content']) === '') {
                continue;
            }

            $role = $msg['role'] === 'assistant' ? 'assistant' : 'user';
            $content = trim($msg['content']);

            // Buang jika pesan pertama adalah assistant
            if (empty($cleaned) && $role === 'assistant') {
                continue;
            }

            // Gabungkan jika role sama dengan pesan sebelumnya
            if (!empty($cleaned) && end($cleaned)['role'] === $role) {
                $lastIndex = count($cleaned) - 1;
                $cleaned[$lastIndex]['content'] .= "\n\n" . $content;
            } else {
                $cleaned[] = [
                    'role' => $role,
                    'content' => $content,
                ];
            }
        }

        $trimmedCurrent = trim($currentMessage);

        if (!empty($cleaned) && end($cleaned)['role'] === 'user') {
            $lastIndex = count($cleaned) - 1;
            $cleaned[$lastIndex]['content'] .= "\n\n" . $trimmedCurrent;
        } else {
            $cleaned[] = [
                'role' => 'user',
                'content' => $trimmedCurrent,
            ];
        }

        return $cleaned;
    }

    /**
     * Membangun system prompt yang sangat spesifik dan ketat sesuai role user.
     * Menerapkan Zero Trust Architecture & Aturan Penyajian Rapi.
     */
    public function buildSystemPrompt(User $user, string $activeRole): string
    {
        $base = <<<PROMPT
Anda adalah asisten AI resmi platform "Pusat Laundry" (Indonesia).
Anda berbicara dengan pengguna bernama "{$user->name}" yang saat ini bertindak dalam role "{$activeRole}".
Bahasa komunikasi: Bahasa Indonesia yang sopan, ramah, solutif, ringkas, dan jelas.

=== ATURAN FORMAT PENYAJIAN JAWABAN (WAJIB DIPATUHI) ===
1. Gaya Bahasa: Bahasa Indonesia santai-sopan, ringkas, langsung ke inti jawaban tanpa basa-basi berlebihan.
2. DILARANG KERAS menggunakan heading markdown besar (# atau ##) karena merusak tampilan chat widget. Gunakan tanda poin (-) atau penekanan tebal (**teks**) saja.
3. Terjemahkan selalu kode status internal ke bahasa manusia yang nyaman dibaca (contoh: "menunggu_konfirmasi" -> "Menunggu Konfirmasi Toko", "di_toko" -> "Tiba di Toko Laundry", "diproses" -> "Sedang Dicuci & Diproses", "siap_diantar" -> "Bersih & Siap Diantar").
4. Format angka uang wajib diawali simbol Rupiah yang rapi (contoh: "Rp 25.000", bukan "25000").
5. JANGAN PERNAH menampilkan istilah teknis internal seperti nama tool/function (misal `get_my_active_orders`), nama tabel database, atau ID internal yang tidak perlu.
6. Kejujuran Data: Jika data tidak ditemukan atau Anda tidak memiliki aksesnya, sampaikan secara jujur dan sopan tanpa mengarang informasi (no hallucinations).

=== PRINSIP ZERO TRUST & INTEGRITAS DATA (WAJIB DIPATUHI) ===
1. ANDA HANYA MEMILIKI AKSES BACA (READ-ONLY) KE DATA SISTEM.
2. ANDA TIDAK MEMILIKI ALAT/TOOL APA PUN UNTUK MENGUBAH, MENAMBAH, ATAU MENGHAPUS DATA (State-Changing Actions).
3. Jika pengguna meminta tindakan seperti membatalkan pesanan, mengubah harga, meng-approve toko, menyetujui refund/dispute, atau mengubah status:
   - JANGAN PERNAH mengklaim bahwa Anda telah melakukannya.
   - JELASKAN bahwa Anda adalah asisten AI yang tidak berwenang mengeksekusi tindakan tersebut secara langsung.
   - ARAHKAN pengguna ke halaman, tombol, atau form resmi di dalam aplikasi Pusat Laundry yang relevan.
4. Jangan pernah membocorkan data sensitif pengguna lain (nomor telepon mentah, password, token, atau data toko kompetitor individual).
PROMPT;

        $roleSpecific = match ($activeRole) {
            'customer' => <<<ROLE
=== PEDOMAN ROLE CUSTOMER ===
- Anda membantu pelanggan mencari informasi toko, cek tarif layanan, cek status order aktif mereka, posisi kurir yang sedang bertugas, notifikasi pesanan, dan FAQ seputar cara order serta aturan pembatalan.
- Aturan Cancel Order: Pembatalan pesanan HANYA bisa dilakukan jika status masih "Menunggu Konfirmasi" atau "Menunggu Kurir". Jika pakaian sudah dijemput ("dijemput"), pembatalan mandiri tidak dapat dilakukan.
- Membuka Dispute: Jika customer mengeluhkan masalah (misal cucian rusak/hilang/berat tidak pas), gunakan tool `prepare_dispute_draft` untuk mengumpulkan info, lalu arahkan mereka untuk mengonfirmasi dan mengirim melalui tombol resmi di halaman pesanan.
- JANGAN PERNAH mengeksekusi pembatalan order atau pembukaan dispute secara langsung.
ROLE,
            'owner_toko', 'admin_toko' => <<<ROLE
=== PEDOMAN ROLE MITRA TOKO ({$activeRole}) ===
- Anda membantu mitra toko melihat ringkasan omzet harian/mingguan, tren order, performa toko, dan rating ulasan pelanggan.
- Saran Tarif: Anda dapat membandingkan tarif toko dengan rata-rata pasar di sekitarnya secara agregat & anonim. JANGAN membocorkan identitas atau nama toko kompetitor tertentu.
- Balas Ulasan: Jika diminta membalas review, buatkan draft teks respon yang sopan. Berikan instruksi bahwa pemilik toko harus menyalin teks tersebut dan mengirimnya manual melalui dashboard.
- Dispute Toko: Tampilkan ringkasan sengketa yang melibatkan toko ini agar owner bisa segera menyiapkan klarifikasi.
ROLE,
            'platform_owner' => <<<ROLE
=== PEDOMAN ROLE PLATFORM OWNER ===
- Anda adalah penasihat eksekutif platform tertinggi.
- Anda memiliki akses ke seluruh metrik operasional, tren pertumbuhan toko, ringkasan sengketa/dispute, deteksi potensi anomali, serta LAPORAN KEUANGAN DAN KOMISI PLATFORM.
- Query Audit Log: Anda dapat membantu mencari riwayat audit log aktivitas admin dan staf.
- Moderasi Toko: Anda dapat membantu merangkum dokumen legalitas calon mitra toko, tetapi tegaskan bahwa keputusan final approve/reject wajib dilakukan via form moderasi resmi.
ROLE,
            'admin_owner' => <<<ROLE
=== PEDOMAN ROLE ADMIN OWNER ===
- Anda membantu tim operasional dan moderasi platform mengelola toko, memeriksa keluhan dispute, memantau user, dan meninjau dokumen legalitas.
- !!! KEBIJAKAN PRIVASI KEUANGAN KETAT !!!
  Admin Owner TIDAK MEMILIKI AKSES ke data komisi inti platform, laporan keuangan menyeluruh, atau pencairan saldo (settlement).
  Jika Admin Owner menanyakan data komisi atau keuangan platform, Anda WAJIB menolaknya dengan sopan dan menyatakan bahwa data keuangan hanya dapat diakses oleh Platform Owner.
- Anda tidak boleh mengambil keputusan approve/reject toko atau suspend toko secara otomatis. Arahkan selalu ke menu moderasi.
ROLE,
            'kurir' => <<<ROLE
=== PEDOMAN ROLE MITRA KURIR ===
- Anda membantu kurir memahami tugas penjemputan & pengantaran, prosedur penagihan pembayaran tunai (COD), dan verifikasi keselamatan barang cucian.
- Penagihan COD: Ingatkan kurir untuk menagih nominal tunai sesuai total tagihan di aplikasi dan memastikan customer menerima pakaian sebelum mengonfirmasi status selesai.
ROLE,
            default => "Bantu pengguna sesuai informasi umum yang tersedia."
        };

        return $base . "\n\n" . $roleSpecific;
    }

    /**
     * Fallback handler cerdas untuk testing lokal / unit testing tanpa real API key Anthropic.
     * Menguji flow tool-calling, otorisasi role, dan masking secara deterministik tanpa JSON mentah.
     * Tetap melalui authorization check per-function via executeTool().
     */
    public function handleLocalOrTestChat(string $userMessage, User $user, string $activeRole, array $toolsSchema): array
    {
        $lowerMsg = strtolower($userMessage);
        $toolsUsed = [];
        $reply = '';

        // 1. INTENT CUSTOMER: CANCEL / BATALKAN PESANAN (Zero-trust direction)
        if (in_array($activeRole, ['customer']) && (str_contains($lowerMsg, 'cancel') || str_contains($lowerMsg, 'batal'))) {
            if (str_contains($lowerMsg, 'kebijakan') || str_contains($lowerMsg, 'syarat') || str_contains($lowerMsg, 'aturan')) {
                $toolsUsed[] = 'get_cancellation_policy';
                $policy = $this->toolRegistry->executeTool('get_cancellation_policy', ['topik' => 'cancel'], $user, $activeRole);
                $reply = "**Kebijakan Pembatalan Pesanan di Pusat Laundry:**\n\n" .
                    $policy['isi'] . "\n\n" .
                    "💡 *Catatan:* Sebagai asisten AI, saya tidak memiliki izin untuk membatalkan pesanan secara langsung. Silakan buka menu **Pesanan Saya** dan tekan tombol **Batalkan Pesanan**.";
            } else {
                $reply = "Demi keamanan transaksi, sebagai asisten AI saya TIDAK BISA membatalkan pesanan Anda secara langsung.\n\n" .
                    "Anda dapat membatalkannya sendiri melalui menu **Pesanan Saya** selama pakaian belum dijemput oleh kurir (status *Menunggu Konfirmasi* atau *Menunggu Kurir*).";
            }
        }

        // 2. INTENT CUSTOMER: DISPUTE / KOMPLAIN / KELUHAN
        elseif (in_array($activeRole, ['customer']) && (str_contains($lowerMsg, 'dispute') || str_contains($lowerMsg, 'komplain') || str_contains($lowerMsg, 'rusak') || str_contains($lowerMsg, 'hilang'))) {
            $toolsUsed[] = 'prepare_dispute_draft';
            preg_match('/(\d+)/', $lowerMsg, $matches);
            $orderId = isset($matches[1]) ? (int) $matches[1] : 1;

            $draft = $this->toolRegistry->executeTool('prepare_dispute_draft', [
                'order_id' => $orderId,
                'kategori_masalah' => 'barang_rusak',
                'deskripsi_masalah' => 'Keluhan disampaikan melalui asisten AI',
            ], $user, $activeRole);

            if (isset($draft['error'])) {
                $reply = "⚠️ " . $draft['error'];
            } else {
                $reply = "Saya telah mencatat ringkasan kendala Anda untuk **Pesanan #{$orderId}**:\n\n" .
                    "- **Kategori:** Barang Rusak / Kendala Layanan\n" .
                    "- **Langkah Selanjutnya:** Silakan buka detail pesanan tersebut, lalu klik tombol **Laporkan Masalah / Ajukan Dispute** untuk verifikasi resmi oleh admin platform.";
            }
        }

        // 3. INTENT CUSTOMER: CEK PESANAN / STATUS / ORDER
        elseif (in_array($activeRole, ['customer']) && (
            str_contains($lowerMsg, 'order') ||
            str_contains($lowerMsg, 'pesanan') ||
            str_contains($lowerMsg, 'status') ||
            str_contains($lowerMsg, 'laundry') ||
            str_contains($lowerMsg, 'cucian')
        )) {
            // Cek detail order spesifik jika ada nomor ID
            if (preg_match('/(?:detail|cek|nomor|no|#)\s*(\d+)/', $lowerMsg, $matches)) {
                $toolsUsed[] = 'get_order_detail';
                $orderDetail = $this->toolRegistry->executeTool('get_order_detail', ['order_id' => (int) $matches[1]], $user, $activeRole);

                if (isset($orderDetail['error'])) {
                    $reply = "⚠️ " . $orderDetail['error'];
                } else {
                    $reply = "**Detail Pesanan #{$orderDetail['id']}**\n\n" .
                        "- **Toko Laundry:** {$orderDetail['toko']['nama']}\n" .
                        "- **Layanan:** {$orderDetail['tarif']['jenis_layanan']} ({$orderDetail['tarif']['harga_formatted']})\n" .
                        "- **Status Saat Ini:** **{$orderDetail['status']}**\n" .
                        "- **Kurir:** {$orderDetail['kurir']['nama']}\n" .
                        "- **Total Biaya:** {$orderDetail['total_biaya_formatted']}\n" .
                        "- **Alamat Jemput:** {$orderDetail['alamat_jemput']}";
                }
            } else {
                $toolsUsed[] = 'get_my_active_orders';
                $ordersData = $this->toolRegistry->executeTool('get_my_active_orders', [], $user, $activeRole);
                $orders = $ordersData['orders'] ?? [];

                if (empty($orders)) {
                    $reply = "Saat ini Anda **tidak memiliki pesanan cucian yang sedang aktif**.\n\nSilakan jelajahi marketplace kami untuk menemukan toko laundry terdekat dan membuat pesanan baru!";
                } else {
                    $lines = ["Berikut daftar pesanan cucian aktif Anda:"];
                    foreach ($orders as $o) {
                        $lines[] = "- **Order #{$o['id']}** di *{$o['toko']}* ({$o['layanan']})\n  Status: **{$o['status']}** | Kurir: {$o['kurir']} | Biaya: {$o['total_biaya']}";
                    }
                    $lines[] = "\nUntuk detail pelacakan lengkap, Anda dapat melihatnya langsung di menu **Pesanan Saya**.";
                    $reply = implode("\n", $lines);
                }
            }
        }

        // 4. INTENT CUSTOMER: TRACKING KURIR
        elseif (in_array($activeRole, ['customer']) && (str_contains($lowerMsg, 'kurir') || str_contains($lowerMsg, 'posisi') || str_contains($lowerMsg, 'lokasi'))) {
            preg_match('/(\d+)/', $lowerMsg, $matches);
            $orderId = isset($matches[1]) ? (int) $matches[1] : 1;

            $toolsUsed[] = 'get_kurir_location';
            $loc = $this->toolRegistry->executeTool('get_kurir_location', ['order_id' => $orderId], $user, $activeRole);

            if (isset($loc['error'])) {
                $reply = "⚠️ " . $loc['error'];
            } else {
                $reply = "**Informasi Pelacakan Kurir untuk Pesanan #{$orderId}:**\n\n" .
                    "- **Status Pesanan:** {$loc['status_order']}\n" .
                    "- **Kurir Bertugas:** {$loc['kurir_name']}\n" .
                    "- **Pembaruan Terakhir:** {$loc['terakhir_update']}\n\n" .
                    "Peta pelacakan real-time dapat dilihat langsung di halaman detail pesanan Anda.";
            }
        }

        // 5. INTENT CUSTOMER: CARI TOKO / TARIF
        elseif (in_array($activeRole, ['customer']) && (str_contains($lowerMsg, 'cari') || str_contains($lowerMsg, 'rekomendasi') || str_contains($lowerMsg, 'toko') || str_contains($lowerMsg, 'tarif') || str_contains($lowerMsg, 'harga'))) {
            $toolsUsed[] = 'search_toko_laundry';
            $tokoData = $this->toolRegistry->executeTool('search_toko_laundry', ['limit' => 3], $user, $activeRole);
            $tokos = $tokoData['tokos'] ?? [];

            if (empty($tokos)) {
                $reply = "Belum ada rekomendasi toko laundry yang sesuai saat ini. Silakan kunjungi menu Beranda untuk melihat seluruh mitra laundry terdaftar.";
            } else {
                $lines = ["Berikut beberapa rekomendasi toko laundry terpercaya untuk Anda:"];
                foreach ($tokos as $t) {
                    $rating = isset($t['rating_rata_rata']) ? "⭐ {$t['rating_rata_rata']}" : "Baru";
                    $lines[] = "- **{$t['nama']}** ({$rating})\n  Alamat: {$t['alamat']}";
                }
                $lines[] = "\nAnda dapat memilih toko dan melakukan pemesanan langsung dari aplikasi.";
                $reply = implode("\n", $lines);
            }
        }

        // 6. INTENT CUSTOMER: NOTIFIKASI SAYA
        elseif (in_array($activeRole, ['customer']) && str_contains($lowerMsg, 'notifikasi')) {
            $toolsUsed[] = 'get_my_notifications';
            $notifData = $this->toolRegistry->executeTool('get_my_notifications', ['limit' => 3], $user, $activeRole);
            $notifs = $notifData['notifications'] ?? [];

            if (empty($notifs)) {
                $reply = "Belum ada notifikasi baru untuk akun Anda.";
            } else {
                $lines = ["Berikut pembaruan notifikasi terbaru Anda:"];
                foreach ($notifs as $n) {
                    $lines[] = "- **{$n['title']}**: {$n['message']} *({$n['time']})*";
                }
                $reply = implode("\n", $lines);
            }
        }

        // 7. INTENT KURIR: TUGAS & COD
        elseif ($activeRole === 'kurir' && (str_contains($lowerMsg, 'tugas') || str_contains($lowerMsg, 'jemput') || str_contains($lowerMsg, 'antar') || str_contains($lowerMsg, 'cod') || str_contains($lowerMsg, 'bayar') || str_contains($lowerMsg, 'panduan'))) {
            $reply = "**Panduan Tugas Mitra Kurir Pusat Laundry:**\n\n" .
                "1. **Penjemputan:** Ambil tugas yang tersedia di tab *Tugas Baru*, datangi alamat pelanggan, dan konfirmasi saat pakaian telah dijemput.\n" .
                "2. **Tiba di Toko:** Serahkan pakaian kotor ke toko laundry dan pastikan staf melakukan penimbangan.\n" .
                "3. **Pengantaran & Pembayaran COD:** Tagih pembayaran tunai sesuai nominal tagihan yang tertera di aplikasi saat menyerahkan cucian bersih kepada pelanggan.";
        }

        // 8. INTENT ADMIN_OWNER MENANYAKAN KOMISI / KEUANGAN (WAJIB DITOLAK SESUAI ZERO TRUST & POLICY)
        elseif ($activeRole === 'admin_owner' && (
            str_contains($lowerMsg, 'komisi') ||
            str_contains($lowerMsg, 'keuangan') ||
            str_contains($lowerMsg, 'omzet') ||
            str_contains($lowerMsg, 'settlement') ||
            str_contains($lowerMsg, 'payout') ||
            str_contains($lowerMsg, 'pendapatan platform')
        )) {
            $reply = "🔒 **Akses Ditolak:** Sebagai Admin Owner, Anda **tidak memiliki akses** atau wewenang untuk melihat data komisi, saldo settlement, atau laporan keuangan platform. Kebijakan ini dilindungi sistem dan data keuangan hanya dapat diakses eksklusif oleh **Platform Owner**.";
        }

        // 9. INTENT MITRA TOKO: OMZET & PERFORMA
        elseif (in_array($activeRole, ['owner_toko', 'admin_toko']) && (str_contains($lowerMsg, 'omzet') || str_contains($lowerMsg, 'performa') || str_contains($lowerMsg, 'pendapatan') || str_contains($lowerMsg, 'order'))) {
            $toolsUsed[] = 'get_toko_performance';
            $perf = $this->toolRegistry->executeTool('get_toko_performance', ['periode' => 'minggu_ini'], $user, $activeRole);

            if (isset($perf['error'])) {
                $reply = "⚠️ " . $perf['error'];
            } else {
                $omzet = isset($perf['omzet_formatted']) ? $perf['omzet_formatted'] : 'Rp ' . number_format($perf['omzet'] ?? 0, 0, ',', '.');
                $totalOrder = $perf['total_orders'] ?? 0;
                $selesai = $perf['orders_selesai'] ?? 0;

                $reply = "**Ringkasan Performa Toko Anda (Minggu Ini):**\n\n" .
                    "- **Total Omzet:** **{$omzet}**\n" .
                    "- **Total Pesanan Masuk:** {$totalOrder} pesanan\n" .
                    "- **Pesanan Berhasil Selesai:** {$selesai} pesanan\n\n" .
                    "Grafik transaksi dan rincian per layanan dapat diakses melalui Dashboard Toko.";
            }
        }

        // 10. INTENT MITRA TOKO: BANDINGKAN TARIF PASAR
        elseif (in_array($activeRole, ['owner_toko', 'admin_toko']) && (str_contains($lowerMsg, 'tarif') || str_contains($lowerMsg, 'harga') || str_contains($lowerMsg, 'pasar'))) {
            $toolsUsed[] = 'get_tarif_comparison';
            $comp = $this->toolRegistry->executeTool('get_tarif_comparison', [], $user, $activeRole);

            if (isset($comp['error'])) {
                $reply = "⚠️ " . $comp['error'];
            } else {
                $reply = "**Analisa Perbandingan Tarif Pasar:**\n\n" .
                    "- **Rata-rata Tarif Pasar:** {$comp['rata_rata_pasar_formatted']}\n" .
                    "- **Saran Strategi:** {$comp['rekomendasi']}\n\n" .
                    "💡 *Catatan:* Perbandingan ini dihitung secara agregat tanpa membocorkan identitas toko kompetitor.";
            }
        }

        // 11. INTENT MITRA TOKO: ULASAN & DRAFT BALASAN
        elseif (in_array($activeRole, ['owner_toko', 'admin_toko']) && (str_contains($lowerMsg, 'ulasan') || str_contains($lowerMsg, 'review') || str_contains($lowerMsg, 'rating') || str_contains($lowerMsg, 'balas'))) {
            $toolsUsed[] = 'get_toko_reviews';
            $revData = $this->toolRegistry->executeTool('get_toko_reviews', ['limit' => 3], $user, $activeRole);

            if (isset($revData['error'])) {
                $reply = "⚠️ " . $revData['error'];
            } else {
                $avgRating = $revData['rating_rata_rata'] ?? '5.0';
                $reply = "**Ringkasan Ulasan Toko Anda:**\n\n" .
                    "- **Rating Rata-rata:** ⭐ **{$avgRating} / 5.0**\n" .
                    "- **Total Ulasan Diterima:** {$revData['total_ulasan']} ulasan\n\n" .
                    "Untuk membalas ulasan pelanggan, silakan gunakan menu ulasan pada dashboard toko Anda.";
            }
        }

        // 12. INTENT PLATFORM / ADMIN OWNER: ANALITIK PLATFORM
        elseif (in_array($activeRole, ['platform_owner', 'admin_owner']) && (str_contains($lowerMsg, 'analitik') || str_contains($lowerMsg, 'tren') || str_contains($lowerMsg, 'pertumbuhan') || str_contains($lowerMsg, 'statistik'))) {
            $toolsUsed[] = 'get_platform_analytics';
            $stats = $this->toolRegistry->executeTool('get_platform_analytics', ['periode' => 'minggu_ini'], $user, $activeRole);

            if (isset($stats['error'])) {
                $reply = "⚠️ " . $stats['error'];
            } else {
                $reply = "**Ringkasan Analitik Platform Pusat Laundry:**\n\n" .
                    "- **Total Toko Terdaftar:** {$stats['total_toko']} toko\n" .
                    "- **Total Pesanan Berjalan:** {$stats['total_orders']} pesanan\n" .
                    "- **Pesanan Selesai:** {$stats['orders_completed']} pesanan\n" .
                    "- **Tingkat Penyelesaian:** {$stats['completion_rate']}%";
            }
        }

        // 13. INTENT PLATFORM / ADMIN OWNER: DETEKSI ANOMALI
        elseif (in_array($activeRole, ['platform_owner', 'admin_owner']) && (str_contains($lowerMsg, 'anomali') || str_contains($lowerMsg, 'curiga') || str_contains($lowerMsg, 'fraud') || str_contains($lowerMsg, 'masalah'))) {
            $toolsUsed[] = 'detect_anomalies';
            $anomalies = $this->toolRegistry->executeTool('detect_anomalies', [], $user, $activeRole);

            if (isset($anomalies['error'])) {
                $reply = "⚠️ " . $anomalies['error'];
            } else {
                $reply = "**Hasil Deteksi Anomali Sistem:**\n\n" .
                    "- **Toko Rating Rendah (< 3.0):** {$anomalies['low_rating_tokos_count']} toko\n" .
                    "- **Tingkat Pembatalan Tinggi (> 25%):** {$anomalies['high_cancellation_count']} toko\n" .
                    "- **Status Anomali:** " . ($anomalies['status_summary'] ?? 'Sistem terpantau normal dan stabil.');
            }
        }

        // 14. DEFAULT FALLBACK RAMAH
        else {
            $reply = "Halo **{$user->name}**, saya asisten AI resmi Pusat Laundry untuk role **" . ucwords(str_replace('_', ' ', $activeRole)) . "**.\n\n" .
                "Ada yang bisa saya bantu hari ini? Anda dapat menanyakan seputar:\n" .
                "- Pelacakan pesanan cucian aktif dan detail transaksi\n" .
                "- Informasi rekomendasi toko laundry dan tarif layanan\n" .
                "- Panduan operasional, FAQ, dan kebijakan pembatalan pesanan.";
        }

        return [
            'reply' => $reply,
            'tools_used' => array_values(array_unique($toolsUsed)),
            'token_usage' => [
                'input_tokens' => 150,
                'output_tokens' => 100,
            ],
            'is_demo' => true,
        ];
    }
}
