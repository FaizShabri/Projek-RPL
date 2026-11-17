<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class PusatLaundryNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public string $notificationType;
    public string $title;
    public string $message;
    public ?int $orderId;
    public ?string $actionUrl;
    public string $idempotencyKey;
    public array $metadata;

    public function __construct(
        string $notificationType,
        string $title,
        string $message,
        ?int $orderId = null,
        ?string $actionUrl = null,
        string $idempotencyKey = '',
        array $metadata = []
    ) {
        $this->notificationType = $notificationType;
        $this->title = $title;
        $this->message = $message;
        $this->orderId = $orderId;
        $this->actionUrl = $actionUrl;
        $this->idempotencyKey = $idempotencyKey;
        $this->metadata = $metadata;
    }

    /**
     * Tentukan channel pengiriman.
     * In-App: database + real-time broadcast.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Representasi array untuk tabel database notifications.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'            => $this->notificationType,
            'title'           => $this->title,
            'message'         => $this->message,
            'order_id'        => $this->orderId,
            'action_url'      => $this->actionUrl,
            'idempotency_key' => $this->idempotencyKey,
            'metadata'        => $this->metadata,
        ];
    }

    /**
     * Representasi broadcast real-time ke channel privat user.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id'              => $this->id,
            'type'            => $this->notificationType,
            'title'           => $this->title,
            'message'         => $this->message,
            'order_id'        => $this->orderId,
            'action_url'      => $this->actionUrl,
            'idempotency_key' => $this->idempotencyKey,
            'created_at'      => now()->toIso8601String(),
        ]);
    }
}
