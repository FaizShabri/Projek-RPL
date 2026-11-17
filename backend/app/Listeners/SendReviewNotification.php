<?php

namespace App\Listeners;

use App\Events\ReviewSubmitted;
use App\Models\User;
use App\Notifications\PusatLaundryNotification;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendReviewNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationPreferenceService $preferenceService;

    public function __construct(NotificationPreferenceService $preferenceService)
    {
        $this->preferenceService = $preferenceService;
    }

    public function handle(ReviewSubmitted $event): void
    {
        $order = $event->order;
        $reviews = $event->reviews;

        // 1. Notifikasi Toko jika ada review toko
        if (isset($reviews['toko']) && $order->toko) {
            $tokoReview = $reviews['toko'];
            $owner = $order->toko->owner;
            if ($owner) {
                $this->sendSafely(
                    $owner,
                    'new_review',
                    'Ulasan Baru untuk Toko Anda ⭐' . $tokoReview->rating,
                    "Pelanggan memberikan rating {$tokoReview->rating}/5 untuk Pesanan #{$order->id}: \"{$tokoReview->komentar}\"",
                    $order->id,
                    '/owner-toko',
                    "review_toko_{$tokoReview->id}"
                );
            }
        }

        // 2. Notifikasi Kurir jika ada review kurir
        if (isset($reviews['kurir']) && $order->kurir_id) {
            $kurirReview = $reviews['kurir'];
            $kurir = $order->kurir ?: User::find($order->kurir_id);
            if ($kurir) {
                $this->sendSafely(
                    $kurir,
                    'new_review',
                    'Ulasan Pengantaran Baru ⭐' . $kurirReview->rating,
                    "Pelanggan memberi rating {$kurirReview->rating}/5 untuk layanan antar Pesanan #{$order->id}.",
                    $order->id,
                    '/kurir',
                    "review_kurir_{$kurirReview->id}"
                );
            }
        }
    }

    protected function sendSafely($recipient, $type, $title, $message, $orderId, $actionUrl, $key): void
    {
        try {
            if (!$this->preferenceService->isEnabled($recipient, $type)) return;

            $alreadySent = $recipient->notifications()->where('data', 'like', "%{$key}%")->exists();
            if ($alreadySent) return;

            $recipient->notify(new PusatLaundryNotification(
                notificationType: $type,
                title: $title,
                message: $message,
                orderId: $orderId,
                actionUrl: $actionUrl,
                idempotencyKey: $key
            ));
        } catch (\Throwable $e) {
            Log::error('Gagal kirim notif review: ' . $e->getMessage());
        }
    }
}
