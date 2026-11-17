<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\TokoRegistered;
use App\Models\User;
use App\Notifications\PusatLaundryNotification;
use App\Services\Notification\NotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendTokoRegisteredNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationPreferenceService $preferenceService;

    public function __construct(NotificationPreferenceService $preferenceService)
    {
        $this->preferenceService = $preferenceService;
    }

    public function handle(TokoRegistered $event): void
    {
        $toko = $event->toko;
        $owner = $event->owner;

        // Notifikasi Admin Owner & Platform Owner ada pendaftaran toko baru
        $admins = User::role([UserRole::ADMIN_OWNER->value, UserRole::PLATFORM_OWNER->value])->get();
        foreach ($admins as $admin) {
            $this->sendSafely(
                $admin,
                'toko_approval',
                'Toko Baru Menunggu Approval',
                "Mitra \"{$toko->nama}\" (Pemilik: {$owner->name}) telah mendaftar dan menunggu verifikasi approval.",
                null,
                '/admin-owner',
                "toko_reg_{$toko->id}_admin_{$admin->id}"
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
            Log::error('Gagal kirim notif toko registered: ' . $e->getMessage());
        }
    }
}
