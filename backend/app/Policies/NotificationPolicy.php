<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPolicy
{
    /**
     * Memastikan user HANYA dapat melihat notifikasinya sendiri.
     */
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $this->isOwner($user, $notification);
    }

    /**
     * Memastikan user HANYA dapat menandai notifikasinya sendiri.
     */
    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $this->isOwner($user, $notification);
    }

    /**
     * Memastikan user HANYA dapat menghapus notifikasinya sendiri.
     */
    public function delete(User $user, DatabaseNotification $notification): bool
    {
        return $this->isOwner($user, $notification);
    }

    protected function isOwner(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === User::class && (int) $notification->notifiable_id === (int) $user->id;
    }
}
