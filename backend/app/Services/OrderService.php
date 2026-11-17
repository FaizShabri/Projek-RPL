<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Events\ReviewSubmitted;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Review;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * STATE MACHINE ORDER STATUS & ATURAN TRANSISI
 * ============================================================================
 * 
 *                  [ Customer Buat Order ]
 *                             │
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │ menunggu_konfirmasi │ ◄── [Cancel Window: Buka]
 *                  └──────────┬──────────┘
 *                             │ (Toko Konfirmasi)
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │   menunggu_kurir    │ ◄── [Cancel Window: Buka]
 *                  └──────────┬──────────┘
 *                             │ (Kurir Terima Tugas & Jemput)
 *          ═══════════════════╪══════════════════════════════════
 *          BATAS CANCEL WINDOW│ (Tertutup setelah pakaian dijemput)
 *          ═══════════════════╪══════════════════════════════════
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │      dijemput       │
 *                  └──────────┬──────────┘
 *                             │ (Kurir Tiba di Toko)
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │       di_toko       │
 *                  └──────────┬──────────┘
 *                             │ (Toko Input Berat -> Auto Hitung Biaya)
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │menunggu_pembayaran_ │ (Info Tagihan COD ke Customer)
 *                  │        info         │
 *                  └──────────┬──────────┘
 *                             │ (Toko Mulai Cuci)
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │      diproses       │
 *                  └──────────┬──────────┘
 *                             │ (Toko Selesai Cuci & Packing)
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │    siap_diantar     │
 *                  └──────────┬──────────┘
 *                             │ (Kurir Ambil & Antar)
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │       diantar       │
 *                  └──────────┬──────────┘
 *                             │ (Kurir Serah Terima & Terima Uang Tunai COD)
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │     cod_selesai     │
 *                  └──────────┬──────────┘
 *                             │ (Konfirmasi Penyelesaian)
 *                             ▼
 *                  ┌─────────────────────┐
 *                  │       selesai       │ ──► [Buka Opsi Review Toko & Kurir]
 *                  └─────────────────────┘
 * 
 * Terminal State: 'selesai' dan 'dibatalkan'.
 */
class OrderService
{
    /**
     * Matriks transisi yang sah dan role yang berwenang.
     * Format: [current_status][target_status] => [authorized_roles]
     */
    protected const TRANSITION_MATRIX = [
        'menunggu_konfirmasi' => [
            'menunggu_kurir' => [UserRole::OWNER_TOKO->value, UserRole::ADMIN_TOKO->value, UserRole::PLATFORM_OWNER->value],
            'dibatalkan'     => [UserRole::CUSTOMER->value, UserRole::OWNER_TOKO->value, UserRole::ADMIN_TOKO->value, UserRole::PLATFORM_OWNER->value],
        ],
        'menunggu_kurir' => [
            'dijemput'   => [UserRole::KURIR->value],
            'dibatalkan' => [UserRole::CUSTOMER->value, UserRole::OWNER_TOKO->value, UserRole::ADMIN_TOKO->value, UserRole::PLATFORM_OWNER->value],
        ],
        'dijemput' => [
            'di_toko' => [UserRole::KURIR->value],
        ],
        'di_toko' => [
            'menunggu_pembayaran_info' => [UserRole::OWNER_TOKO->value, UserRole::ADMIN_TOKO->value, UserRole::PLATFORM_OWNER->value],
        ],
        'menunggu_pembayaran_info' => [
            'diproses' => [UserRole::OWNER_TOKO->value, UserRole::ADMIN_TOKO->value, UserRole::PLATFORM_OWNER->value],
        ],
        'diproses' => [
            'siap_diantar' => [UserRole::OWNER_TOKO->value, UserRole::ADMIN_TOKO->value, UserRole::PLATFORM_OWNER->value],
        ],
        'siap_diantar' => [
            'diantar' => [UserRole::KURIR->value],
        ],
        'diantar' => [
            'cod_selesai' => [UserRole::KURIR->value],
        ],
        'cod_selesai' => [
            'selesai' => [UserRole::KURIR->value, UserRole::CUSTOMER->value, UserRole::OWNER_TOKO->value, UserRole::ADMIN_TOKO->value, UserRole::PLATFORM_OWNER->value],
        ],
    ];

    /**
     * 1. Customer membuat order baru.
     */
    public function createOrder(array $data, User $customer): Order
    {
        $result = DB::transaction(function () use ($data, $customer) {
            $tarif = Tarif::where('id', $data['tarif_id'])
                ->where('toko_id', $data['toko_id'])
                ->firstOrFail();

            $order = Order::create([
                'customer_id'   => $customer->id,
                'toko_id'       => $data['toko_id'],
                'tarif_id'      => $tarif->id,
                'status'        => OrderStatus::MENUNGGU_KONFIRMASI,
                'alamat_jemput' => $data['alamat_jemput'],
                'catatan'       => $data['catatan'] ?? null,
                'metode_bayar'  => 'CASH',
            ]);

            $history = OrderStatusHistory::create([
                'order_id'   => $order->id,
                'user_id'    => $customer->id,
                'status'     => OrderStatus::MENUNGGU_KONFIRMASI,
                'keterangan' => 'Pesanan berhasil dibuat oleh pelanggan. Menunggu konfirmasi toko.',
            ]);

            return [$order->load(['toko', 'tarif', 'statusHistories']), $history];
        });

        OrderStatusChanged::dispatch($result[0], $result[1], null, $customer);

        return $result[0];
    }

    /**
     * 2. Toko mengonfirmasi pesanan (menunggu_konfirmasi -> menunggu_kurir).
     */
    public function confirmOrder(Order $order, User $actor, string $activeRole): Order
    {
        $this->ensureCanManageToko($order, $actor, $activeRole);

        return $this->transitionStatus(
            $order,
            OrderStatus::MENUNGGU_KURIR,
            $actor,
            $activeRole,
            'Toko telah mengonfirmasi pesanan. Tugas penjemputan ditawarkan ke mitra kurir.'
        );
    }

    /**
     * 3. Kurir menerima / mengambil tugas penjemputan.
     */
    public function acceptPickupTask(Order $order, User $kurir, string $activeRole): Order
    {
        if ($activeRole !== UserRole::KURIR->value) {
            throw new InvalidOrderTransitionException('Hanya user dengan role Kurir yang dapat menerima tugas pengantaran.');
        }

        $currentStatus = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        if ($currentStatus !== OrderStatus::MENUNGGU_KURIR->value) {
            throw new InvalidOrderTransitionException("Tugas penjemputan tidak tersedia untuk pesanan dengan status '{$currentStatus}'.");
        }

        if ($order->kurir_id && $order->kurir_id !== $kurir->id) {
            throw new InvalidOrderTransitionException('Tugas ini sudah diambil oleh kurir lain.');
        }

        $order->kurir_id = $kurir->id;
        $order->save();

        $history = OrderStatusHistory::create([
            'order_id'   => $order->id,
            'user_id'    => $kurir->id,
            'status'     => OrderStatus::MENUNGGU_KURIR,
            'keterangan' => "Kurir {$kurir->name} telah menerima tugas penjemputan dan bersiap menuju lokasi customer.",
        ]);

        $freshOrder = $order->fresh(['kurir', 'statusHistories', 'toko']);
        OrderStatusChanged::dispatch($freshOrder, $history, OrderStatus::MENUNGGU_KURIR->value, $kurir);

        return $freshOrder;
    }

    /**
     * 4. Kurir menolak / melepas tugas penjemputan kembali ke antrean publik.
     */
    public function releaseTask(Order $order, User $kurir, string $activeRole): Order
    {
        if ($activeRole !== UserRole::KURIR->value) {
            throw new InvalidOrderTransitionException('Hanya kurir yang dapat melepas tugas.');
        }

        if ($order->kurir_id !== $kurir->id) {
            throw new InvalidOrderTransitionException('Anda tidak ditugaskan pada pesanan ini.');
        }

        $currentStatus = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        if (!in_array($currentStatus, [OrderStatus::MENUNGGU_KURIR->value, OrderStatus::SIAP_DIANTAR->value])) {
            throw new InvalidOrderTransitionException('Tugas tidak dapat dilepas pada status saat ini.');
        }

        $order->kurir_id = null;
        $order->save();

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'user_id'    => $kurir->id,
            'status'     => $order->status,
            'keterangan' => "Kurir {$kurir->name} melepas tugas. Pesanan kembali ke antrean tugas kurir.",
        ]);

        return $order->fresh(['kurir', 'statusHistories']);
    }

    /**
     * 5. Penimbangan di toko & kalkulasi biaya otomatis berdasarkan tarif toko.
     * Transisi: di_toko -> menunggu_pembayaran_info.
     */
    public function weighAndCalculateTotal(Order $order, float $berat, User $actor, string $activeRole): Order
    {
        $this->ensureCanManageToko($order, $actor, $activeRole);

        $currentStatus = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        if ($currentStatus !== OrderStatus::DI_TOKO->value) {
            throw new InvalidOrderTransitionException("Penimbangan pakaian hanya dapat dilakukan saat pakaian berada pada status 'di_toko'.");
        }

        if ($berat <= 0) {
            throw new InvalidOrderTransitionException('Berat pakaian harus lebih dari 0 kg.');
        }

        if (!$order->tarif) {
            $order->load('tarif');
        }

        if (!$order->tarif) {
            throw new InvalidOrderTransitionException('Tarif layanan untuk pesanan ini tidak ditemukan.');
        }

        $totalBiaya = round($berat * (float) $order->tarif->harga, 2);

        $result = DB::transaction(function () use ($order, $berat, $totalBiaya, $actor, $activeRole) {
            $order->berat = $berat;
            $order->total_biaya = $totalBiaya;
            $order->status = OrderStatus::MENUNGGU_PEMBAYARAN_INFO;
            $order->save();

            $formattedTotal = 'Rp ' . number_format($totalBiaya, 0, ',', '.');
            $keterangan = "Pakaian telah ditimbang seberat {$berat} kg ({$order->tarif->jenis_layanan}). Total tagihan COD: {$formattedTotal} (Dibayar tunai saat kurir mengantar).";

            $history = OrderStatusHistory::create([
                'order_id'   => $order->id,
                'user_id'    => $actor->id,
                'status'     => OrderStatus::MENUNGGU_PEMBAYARAN_INFO,
                'keterangan' => $keterangan,
            ]);

            return [$order->fresh(['toko', 'tarif', 'statusHistories', 'kurir']), $history];
        });

        OrderStatusChanged::dispatch($result[0], $result[1], OrderStatus::DI_TOKO->value, $actor);

        return $result[0];
    }

    /**
     * 6. Pembatalan pesanan (Cancel Window Validation).
     */
    public function cancelOrder(Order $order, User $actor, string $activeRole, ?string $alasan = null): Order
    {
        $currentStatus = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;

        // Customer hanya boleh cancel di status awal (menunggu_konfirmasi & menunggu_kurir)
        if ($activeRole === UserRole::CUSTOMER->value) {
            if ($order->customer_id !== $actor->id) {
                throw new InvalidOrderTransitionException('Anda tidak memiliki akses untuk membatalkan pesanan ini.');
            }

            if (!in_array($currentStatus, [OrderStatus::MENUNGGU_KONFIRMASI->value, OrderStatus::MENUNGGU_KURIR->value])) {
                throw new InvalidOrderTransitionException(
                    'Pesanan tidak dapat dibatalkan karena pakaian sudah dijemput oleh kurir atau sedang diproses.'
                );
            }
        } else {
            // Toko / Admin
            $this->ensureCanManageToko($order, $actor, $activeRole);
        }

        $result = DB::transaction(function () use ($order, $actor, $alasan) {
            $order->status = OrderStatus::DIBATALKAN;
            $order->save();

            $alasanText = $alasan ? " Alasan: {$alasan}" : '';
            $history = OrderStatusHistory::create([
                'order_id'   => $order->id,
                'user_id'    => $actor->id,
                'status'     => OrderStatus::DIBATALKAN,
                'keterangan' => "Pesanan dibatalkan oleh {$actor->name}.{$alasanText}",
            ]);

            return [$order->fresh(['statusHistories', 'toko', 'kurir']), $history];
        });

        OrderStatusChanged::dispatch($result[0], $result[1], $currentStatus, $actor);

        return $result[0];
    }

    /**
     * 7. Transisi status umum dengan validasi State Machine & Role Authorization.
     */
    public function transitionStatus(
        Order $order,
        OrderStatus|string $targetStatus,
        User $actor,
        string $activeRole,
        ?string $keterangan = null
    ): Order {
        $targetStatusValue = $targetStatus instanceof OrderStatus ? $targetStatus->value : (string) $targetStatus;
        $currentStatusValue = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;

        // Validasi matriks transisi
        if (!isset(self::TRANSITION_MATRIX[$currentStatusValue][$targetStatusValue])) {
            throw new InvalidOrderTransitionException(
                "Transisi status dari '{$currentStatusValue}' ke '{$targetStatusValue}' tidak valid menurut state machine."
            );
        }

        // Validasi otorisasi role
        $allowedRoles = self::TRANSITION_MATRIX[$currentStatusValue][$targetStatusValue];
        if (!in_array($activeRole, $allowedRoles)) {
            throw new InvalidOrderTransitionException(
                "Role '{$activeRole}' tidak memiliki wewenang untuk mengubah status pesanan dari '{$currentStatusValue}' ke '{$targetStatusValue}'."
            );
        }

        // Validasi kepemilikan/penugasan spesifik
        if ($activeRole === UserRole::KURIR->value) {
            // Jika kurir mengubah ke dijemput, assign kurir jika belum
            if ($targetStatusValue === OrderStatus::DIJEMPUT->value) {
                if ($order->kurir_id && $order->kurir_id !== $actor->id) {
                    throw new InvalidOrderTransitionException('Pesanan ini sudah ditugaskan ke kurir lain.');
                }
                $order->kurir_id = $actor->id;
            } elseif ($order->kurir_id !== $actor->id) {
                throw new InvalidOrderTransitionException('Anda bukan kurir yang ditugaskan pada pesanan ini.');
            }
        } elseif (in_array($activeRole, [UserRole::OWNER_TOKO->value, UserRole::ADMIN_TOKO->value])) {
            $this->ensureCanManageToko($order, $actor, $activeRole);
        }

        $result = DB::transaction(function () use ($order, $targetStatusValue, $actor, $keterangan) {
            $order->status = $targetStatusValue;
            $order->save();

            $desc = $keterangan ?? $this->getDefaultTransitionMessage($targetStatusValue, $actor);

            $history = OrderStatusHistory::create([
                'order_id'   => $order->id,
                'user_id'    => $actor->id,
                'status'     => $targetStatusValue,
                'keterangan' => $desc,
            ]);

            return [$order->fresh(['toko', 'tarif', 'kurir', 'statusHistories']), $history];
        });

        OrderStatusChanged::dispatch($result[0], $result[1], $currentStatusValue, $actor);

        return $result[0];
    }

    /**
     * 8. Kirim review pelanggan (toko & kurir terpisah) setelah pesanan 'selesai'.
     */
    public function submitReview(Order $order, User $customer, array $data): array
    {
        if ($order->customer_id !== $customer->id) {
            throw new InvalidOrderTransitionException('Anda bukan pelanggan pemilik pesanan ini.');
        }

        $currentStatus = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        if ($currentStatus !== OrderStatus::SELESAI->value) {
            throw new InvalidOrderTransitionException("Ulasan hanya dapat diberikan setelah pesanan berstatus 'selesai'.");
        }

        // Satu review per order (tidak bisa submit ulang)
        if ($order->reviews()->exists()) {
            throw new InvalidOrderTransitionException('Pesanan ini sudah pernah diulas. Ulasan hanya dapat diberikan satu kali per pesanan.');
        }

        $reviewsCreated = DB::transaction(function () use ($order, $data) {
            $reviewsCreated = [];

            // Review Toko
            if (isset($data['toko_rating'])) {
                $rating = max(1, min(5, (int) $data['toko_rating']));
                $reviewsCreated['toko'] = Review::create([
                    'order_id' => $order->id,
                    'toko_id'  => $order->toko_id,
                    'rating'   => $rating,
                    'komentar' => $data['toko_komentar'] ?? null,
                ]);
            }

            // Review Kurir (jika kurir ada)
            if ($order->kurir_id && isset($data['kurir_rating'])) {
                $rating = max(1, min(5, (int) $data['kurir_rating']));
                $reviewsCreated['kurir'] = Review::create([
                    'order_id' => $order->id,
                    'kurir_id' => $order->kurir_id,
                    'rating'   => $rating,
                    'komentar' => $data['kurir_komentar'] ?? null,
                ]);
            }

            if (empty($reviewsCreated)) {
                throw new InvalidOrderTransitionException('Harap sertakan setidaknya satu rating untuk toko atau kurir.');
            }

            return $reviewsCreated;
        });

        ReviewSubmitted::dispatch($order->fresh(['toko', 'kurir']), $reviewsCreated, $customer);

        return $reviewsCreated;
    }

    /**
     * Helper pesan default histori transisi status.
     */
    protected function getDefaultTransitionMessage(string $status, User $actor): string
    {
        return match ($status) {
            OrderStatus::MENUNGGU_KURIR->value => 'Pesanan siap dijemput oleh mitra kurir.',
            OrderStatus::DIJEMPUT->value => "Pakaian telah dijemput oleh kurir {$actor->name}.",
            OrderStatus::DI_TOKO->value => 'Pakaian telah tiba di toko laundry dan siap ditimbang.',
            OrderStatus::DIPROSES->value => 'Pakaian mulai masuk proses pencucian dan perawatan.',
            OrderStatus::SIAP_DIANTAR->value => 'Pakaian telah bersih, wangi, dan rapi dipacking. Siap diantar kembali.',
            OrderStatus::DIANTAR->value => "Kurir {$actor->name} sedang dalam perjalanan mengantar pakaian bersih ke customer.",
            OrderStatus::COD_SELESAI->value => 'Pakaian telah diterima oleh pelanggan dan pembayaran tunai COD telah lunas.',
            OrderStatus::SELESAI->value => 'Pesanan telah selesai secara tuntas.',
            OrderStatus::DIBATALKAN->value => "Pesanan dibatalkan oleh {$actor->name}.",
            default => "Status pesanan diperbarui menjadi {$status}.",
        };
    }

    /**
     * Memastikan user berhak mengelola toko dari order tersebut.
     */
    protected function ensureCanManageToko(Order $order, User $actor, string $activeRole): void
    {
        if ($activeRole === UserRole::PLATFORM_OWNER->value) {
            return;
        }

        if ($activeRole === UserRole::OWNER_TOKO->value) {
            $toko = $order->toko ?: $order->toko()->first();
            if (!$toko || $toko->owner_id !== $actor->id) {
                throw new InvalidOrderTransitionException('Anda bukan pemilik toko untuk pesanan ini.');
            }
            return;
        }

        if ($activeRole === UserRole::ADMIN_TOKO->value) {
            $toko = $order->toko ?: $order->toko()->first();
            if (!$toko || !$toko->staff()->where('user_id', $actor->id)->exists()) {
                throw new InvalidOrderTransitionException('Anda tidak ditugaskan sebagai admin/staff pada toko pesanan ini.');
            }
            return;
        }

        throw new InvalidOrderTransitionException('Role aktif Anda tidak berwenang mengelola pesanan toko ini.');
    }
}
