<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderStatusChanged;
use App\Models\User;
use App\Notifications\PusatLaundryNotification;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderStatusNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationPreferenceService $preferenceService;

    public function __construct(NotificationPreferenceService $preferenceService)
    {
        $this->preferenceService = $preferenceService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;
        $history = $event->statusHistory;
        $status = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        $tokoName = $order->toko?->nama ?? 'Toko Laundry';

        // 1. NOTIFIKASI CUSTOMER
        $this->notifyCustomer($order, $status, $tokoName, $history);

        // 2. NOTIFIKASI KURIR
        $this->notifyKurir($order, $status, $tokoName, $history);

        // 3. NOTIFIKASI TOKO (Owner & Staff)
        $this->notifyToko($order, $status, $history);
    }

    protected function notifyCustomer($order, string $status, string $tokoName, $history): void
    {
        $customer = $order->customer ?: User::find($order->customer_id);
        if (!$customer) return;

        $notifData = match ($status) {
            OrderStatus::MENUNGGU_KURIR->value => $order->kurir_id ? [
                'type' => 'kurir_assigned',
                'title' => 'Kurir Menerima Tugas Penjemputan',
                'message' => "Kurir " . ($order->kurir?->name ?? 'mitra') . " telah menerima tugas penjemputan untuk Pesanan #{$order->id} dan bersiap menuju lokasi Anda.",
            ] : [
                'type' => 'order_status',
                'title' => 'Pesanan Dikonfirmasi Toko',
                'message' => "Pesanan #{$order->id} telah dikonfirmasi oleh {$tokoName}. Menunggu kurir mengambil tugas penjemputan.",
            ],
            OrderStatus::DIJEMPUT->value => [
                'type' => 'order_status',
                'title' => 'Pakaian Telah Dijemput',
                'message' => "Kurir telah menjemput pakaian kotor Anda untuk Pesanan #{$order->id} dan sedang membawanya ke {$tokoName}.",
            ],
            OrderStatus::DI_TOKO->value => [
                'type' => 'order_status',
                'title' => 'Pakaian Tiba di Toko',
                'message' => "Pakaian untuk Pesanan #{$order->id} telah tiba di {$tokoName} dan siap ditimbang.",
            ],
            OrderStatus::MENUNGGU_PEMBAYARAN_INFO->value => [
                'type' => 'cod_fee', // KRITIS
                'title' => 'Selesai Ditimbang - Info Tagihan COD',
                'message' => "Pakaian Anda ditimbang seberat {$order->berat} kg. Total tagihan COD: Rp " . number_format($order->total_biaya, 0, ',', '.') . " (dibayar tunai saat kurir mengantar).",
            ],
            OrderStatus::DIPROSES->value => [
                'type' => 'order_status',
                'title' => 'Pakaian Sedang Dicuci & Diproses',
                'message' => "Pakaian Pesanan #{$order->id} sedang dalam proses pencucian, pengeringan, dan perawatan di {$tokoName}.",
            ],
            OrderStatus::SIAP_DIANTAR->value => [
                'type' => 'order_status',
                'title' => 'Pakaian Bersih Siap Diantar',
                'message' => "Pakaian Pesanan #{$order->id} sudah bersih, wangi, dan rapi dipacking. Menunggu kurir mengambil pengantaran.",
            ],
            OrderStatus::DIANTAR->value => [
                'type' => 'order_status',
                'title' => 'Kurir Sedang Mengantar ke Alamat Anda',
                'message' => "Kurir sedang dalam perjalanan mengantar cucian bersih Pesanan #{$order->id}. Mohon siapkan uang pas COD.",
            ],
            OrderStatus::COD_SELESAI->value => [
                'type' => 'order_status',
                'title' => 'Pakaian Diterima & COD Selesai',
                'message' => "Pakaian Pesanan #{$order->id} telah diserahkan dan pembayaran COD telah diselesaikan.",
            ],
            OrderStatus::SELESAI->value => [
                'type' => 'order_status',
                'title' => 'Pesanan Selesai - Beri Ulasan',
                'message' => "Pesanan #{$order->id} telah tuntas. Bagikan pengalaman Anda dengan memberikan ulasan untuk toko & kurir!",
            ],
            OrderStatus::DIBATALKAN->value => [
                'type' => 'order_cancelled', // KRITIS
                'title' => 'Pesanan Dibatalkan',
                'message' => "Pesanan #{$order->id} telah dibatalkan.",
            ],
            default => null,
        };

        if ($notifData) {
            $this->sendSafely(
                recipient: $customer,
                type: $notifData['type'],
                title: $notifData['title'],
                message: $notifData['message'],
                orderId: $order->id,
                actionUrl: "/customer/orders",
                idempotencyKey: "customer_{$customer->id}_order_{$order->id}_status_{$status}_h{$history->id}"
            );
        }
    }

    protected function notifyKurir($order, string $status, string $tokoName, $history): void
    {
        // A. Tugas baru tersedia saat status MENUNGGU_KURIR atau SIAP_DIANTAR
        if (in_array($status, [OrderStatus::MENUNGGU_KURIR->value, OrderStatus::SIAP_DIANTAR->value])) {
            $taskLabel = $status === OrderStatus::MENUNGGU_KURIR->value ? 'Penjemputan' : 'Pengantaran';
            $kurirs = User::role(UserRole::KURIR->value)->get();

            foreach ($kurirs as $kurir) {
                $this->sendSafely(
                    recipient: $kurir,
                    type: 'new_task', // KRITIS
                    title: "Tugas {$taskLabel} Baru Tersedia!",
                    message: "Ada tugas {$taskLabel} untuk Pesanan #{$order->id} ({$tokoName}). Buka dashboard kurir untuk mengambil tugas.",
                    orderId: $order->id,
                    actionUrl: "/kurir",
                    idempotencyKey: "kurir_{$kurir->id}_order_{$order->id}_task_{$status}_h{$history->id}"
                );
            }
        }

        // B. Tugas dibatalkan customer untuk kurir yang sedang ditugaskan
        if ($status === OrderStatus::DIBATALKAN->value && $order->kurir_id) {
            $assignedKurir = $order->kurir ?: User::find($order->kurir_id);
            if ($assignedKurir) {
                $this->sendSafely(
                    recipient: $assignedKurir,
                    type: 'order_cancelled', // KRITIS
                    title: 'Tugas Cucian Dibatalkan',
                    message: "Pesanan #{$order->id} yang ditugaskan kepada Anda telah dibatalkan oleh pelanggan/toko.",
                    orderId: $order->id,
                    actionUrl: "/kurir",
                    idempotencyKey: "kurir_{$assignedKurir->id}_order_{$order->id}_cancelled_h{$history->id}"
                );
            }
        }
    }

    protected function notifyToko($order, string $status, $history): void
    {
        $toko = $order->toko;
        if (!$toko) return;

        $recipients = collect();
        if ($toko->owner_id) {
            $owner = $toko->owner ?: User::find($toko->owner_id);
            if ($owner) $recipients->push($owner);
        }

        // Staff toko
        $staffs = $toko->staffUsers;
        foreach ($staffs as $s) {
            $recipients->push($s);
        }

        $recipients = $recipients->unique('id');

        $notifData = match ($status) {
            OrderStatus::MENUNGGU_KONFIRMASI->value => [
                'type' => 'new_order',
                'title' => 'Pesanan Baru Masuk!',
                'message' => "Pesanan baru #{$order->id} menunggu konfirmasi Anda.",
            ],
            OrderStatus::DIBATALKAN->value => [
                'type' => 'order_cancelled', // KRITIS
                'title' => 'Pesanan Dibatalkan',
                'message' => "Pesanan #{$order->id} telah dibatalkan.",
            ],
            OrderStatus::DI_TOKO->value => [
                'type' => 'order_status',
                'title' => 'Pakaian Tiba di Toko',
                'message' => "Pakaian Pesanan #{$order->id} telah diantar kurir. Silakan lakukan penimbangan.",
            ],
            default => null,
        };

        if ($notifData) {
            foreach ($recipients as $recipient) {
                $this->sendSafely(
                    recipient: $recipient,
                    type: $notifData['type'],
                    title: $notifData['title'],
                    message: $notifData['message'],
                    orderId: $order->id,
                    actionUrl: "/owner-toko",
                    idempotencyKey: "toko_{$recipient->id}_order_{$order->id}_status_{$status}_h{$history->id}"
                );
            }
        }
    }

    /**
     * Kirim notifikasi dengan pengecekan preferensi & perlindungan idempotensi.
     */
    protected function sendSafely(
        User $recipient,
        string $type,
        string $title,
        string $message,
        ?int $orderId,
        ?string $actionUrl,
        string $idempotencyKey
    ): void {
        try {
            // 1. Cek preferensi user
            if (!$this->preferenceService->isEnabled($recipient, $type)) {
                return;
            }

            // 2. Cek Idempotensi: pastikan notifikasi dengan idempotency_key yang sama belum pernah ada untuk user ini
            $alreadySent = $recipient->notifications()
                ->where('data', 'like', '%' . $idempotencyKey . '%')
                ->exists();

            if ($alreadySent) {
                return;
            }

            // 3. Kirim via Laravel Notification
            $recipient->notify(new PusatLaundryNotification(
                notificationType: $type,
                title: $title,
                message: $message,
                orderId: $orderId,
                actionUrl: $actionUrl,
                idempotencyKey: $idempotencyKey
            ));
        } catch (\Throwable $e) {
            // Kegagalan pengiriman notifikasi dicatat ke log dan TIDAK MENGGAGALKAN alur order
            Log::error('Gagal mengirim notifikasi order status', [
                'recipient_id' => $recipient->id,
                'type' => $type,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
