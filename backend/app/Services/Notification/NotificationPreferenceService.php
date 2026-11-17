<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\UserNotificationPreference;
use InvalidArgumentException;

class NotificationPreferenceService
{
    /**
     * Definisi seluruh tipe notifikasi dan status kekritisannya.
     * Notifikasi KRITIS (critical = true) TIDAK BOLEH dimatikan oleh user.
     */
    public const TYPES = [
        'cod_fee' => [
            'name' => 'cod_fee',
            'label' => 'Tagihan & Informasi Biaya COD',
            'description' => 'Pemberitahuan hasil penimbangan cucian dan total biaya COD yang wajib dibayar.',
            'critical' => true, // KRITIS: Wajib aktif
            'roles' => ['customer'],
        ],
        'order_cancelled' => [
            'name' => 'order_cancelled',
            'label' => 'Pembatalan Pesanan',
            'description' => 'Pemberitahuan darurat saat pesanan dibatalkan.',
            'critical' => true, // KRITIS: Wajib aktif
            'roles' => ['customer', 'owner_toko', 'admin_toko', 'kurir'],
        ],
        'new_task' => [
            'name' => 'new_task',
            'label' => 'Tugas Penjemputan / Pengantaran Baru',
            'description' => 'Pemberitahuan ketersediaan tugas cucian baru bagi mitra kurir.',
            'critical' => true, // KRITIS: Wajib aktif untuk kurir
            'roles' => ['kurir'],
        ],
        'order_status' => [
            'name' => 'order_status',
            'label' => 'Update Status Cucian Berkala',
            'description' => 'Notifikasi saat laundry dijemput, tiba di toko, diproses, atau selesai.',
            'critical' => false,
            'roles' => ['customer'],
        ],
        'kurir_assigned' => [
            'name' => 'kurir_assigned',
            'label' => 'Penugasan Kurir',
            'description' => 'Notifikasi saat kurir menerima tugas dan bersiap ke lokasi.',
            'critical' => false,
            'roles' => ['customer'],
        ],
        'new_order' => [
            'name' => 'new_order',
            'label' => 'Pesanan Baru Masuk',
            'description' => 'Notifikasi pesanan cucian baru yang masuk ke toko laundry Anda.',
            'critical' => false,
            'roles' => ['owner_toko', 'admin_toko'],
        ],
        'dispute_update' => [
            'name' => 'dispute_update',
            'label' => 'Sengketa & Komplain (Dispute)',
            'description' => 'Notifikasi saat dispute baru dibuka atau ada respon tindak lanjut.',
            'critical' => false,
            'roles' => ['owner_toko', 'admin_toko', 'admin_owner', 'platform_owner', 'customer'],
        ],
        'new_review' => [
            'name' => 'new_review',
            'label' => 'Ulasan & Rating Pelanggan',
            'description' => 'Pemberitahuan ulasan baru dari pelanggan setelah cucian selesai.',
            'critical' => false,
            'roles' => ['owner_toko', 'admin_toko', 'kurir'],
        ],
        'toko_approval' => [
            'name' => 'toko_approval',
            'label' => 'Pendaftaran Toko Baru',
            'description' => 'Pemberitahuan toko baru yang menunggu verifikasi dan approval admin.',
            'critical' => false,
            'roles' => ['admin_owner', 'platform_owner'],
        ],
    ];

    /**
     * Memeriksa apakah suatu tipe notifikasi diaktifkan oleh user.
     * Notifikasi kritis selalu bernilai TRUE.
     */
    public function isEnabled(User $user, string $type): bool
    {
        // Notifikasi kritis selalu aktif
        if (isset(self::TYPES[$type]) && self::TYPES[$type]['critical']) {
            return true;
        }

        $pref = UserNotificationPreference::where('user_id', $user->id)
            ->where('notification_type', $type)
            ->first();

        // Default adalah true jika belum pernah dikonfigurasi
        return $pref ? $pref->is_enabled : true;
    }

    /**
     * Mengambil seluruh preferensi notifikasi user beserta metadata kritis.
     */
    public function getPreferences(User $user): array
    {
        $existing = UserNotificationPreference::where('user_id', $user->id)
            ->pluck('is_enabled', 'notification_type')
            ->toArray();

        $result = [];
        foreach (self::TYPES as $key => $meta) {
            $isEnabled = $meta['critical'] ? true : ($existing[$key] ?? true);

            $result[] = [
                'type' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'critical' => $meta['critical'],
                'is_enabled' => $isEnabled,
                'can_toggle' => !$meta['critical'],
            ];
        }

        return $result;
    }

    /**
     * Memperbarui preferensi notifikasi.
     * Menolak jika ada percobaan mematikan notifikasi kritis.
     *
     * @throws InvalidArgumentException Jika mencoba menonaktifkan notifikasi kritis.
     */
    public function updatePreferences(User $user, array $preferences): void
    {
        foreach ($preferences as $type => $enabled) {
            if (!isset(self::TYPES[$type])) {
                continue;
            }

            // Validasi: Notifikasi kritis TIDAK BOLEH dimatikan
            if (self::TYPES[$type]['critical'] && !$enabled) {
                $label = self::TYPES[$type]['label'];
                throw new InvalidArgumentException("Notifikasi '{$label}' bersifat kritis dan tidak dapat dimatikan demi keamanan transaksi.");
            }

            UserNotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type' => $type,
                ],
                [
                    'is_enabled' => (bool) $enabled,
                ]
            );
        }
    }
}
