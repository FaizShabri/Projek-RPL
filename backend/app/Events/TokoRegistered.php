<?php

namespace App\Events;

use App\Models\Toko;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokoRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Toko $toko;
    public User $owner;

    public function __construct(Toko $toko, User $owner)
    {
        $this->toko = $toko;
        $this->owner = $owner;
    }
}
