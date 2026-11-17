<?php

namespace App\Services\Notification\Channels;

use App\Models\User;

/**
 * Interface extensible untuk channel notifikasi masa depan (Email, WhatsApp, WebPush).
 * Mengizinkan penambahan channel pengiriman tanpa mengubah event maupun listener domain.
 */
interface NotificationChannelInterface
{
    /**
     * Nama unik channel (contoh: 'email', 'whatsapp', 'webpush', 'in_app')
     */
    public function name(): string;

    /**
     * Kirim notifikasi ke user via channel ini.
     *
     * @param User $recipient User penerima
     * @param array $payload Data notifikasi (title, message, order_id, type, metadata)
     */
    public function send(User $recipient, array $payload): void;
}
