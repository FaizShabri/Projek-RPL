<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\DisputeOpened;
use App\Models\User;
use App\Notifications\PusatLaundryNotification;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendDisputeNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationPreferenceService $preferenceService;

    public function __construct(NotificationPreferenceService $preferenceService)
    {
        $this->preferenceService = $preferenceService;
    }

    public function handle(DisputeOpened $event): void
    {
        $dispute = $event->dispute;
        $order = $dispute->order;
        if (!$order) return;

        $toko = $order->toko;

        // 1. Notifikasi Toko (Owner & Staff) jika pelapor bukan toko
        if ($toko && $dispute->dibuka_oleh !== $toko->owner_id) {
            $recipients = collect([$toko->owner])->merge($toko->staffUsers)->filter()->unique('id');
            foreach ($recipients as $recipient) {
                $this->sendSafely(
                    $recipient,
                    'dispute_update',
                    'Dispute / Komplain Baru Dibuka',
                    "Pelanggan mengajukan komplain untuk Pesanan #{$order->id}: \"{$dispute->alasan}\".",
                    $order->id,
                    '/owner-toko',
                    "dispute_{$dispute->id}_toko_{$recipient->id}"
                );
            }
        }

        // 2. Notifikasi Admin Owner & Platform Owner
        $admins = User::role([UserRole::ADMIN_OWNER->value, UserRole::PLATFORM_OWNER->value])->get();
        foreach ($admins as $admin) {
            $this->sendSafely(
                $admin,
                'dispute_update',
                'Dispute Perlu Keputusan Platform',
                "Dispute baru dibuka untuk Pesanan #{$order->id} ({$toko?->nama}). Alasan: {$dispute->alasan}.",
                $order->id,
                '/admin-owner',
                "dispute_{$dispute->id}_admin_{$admin->id}"
            );
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
            Log::error('Gagal kirim notif dispute: ' . $e->getMessage());
        }
    }
}
