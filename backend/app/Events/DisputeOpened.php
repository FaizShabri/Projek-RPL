<?php

namespace App\Events;

use App\Models\Dispute;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeOpened
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Dispute $dispute;
    public User $actor;

    public function __construct(Dispute $dispute, User $actor)
    {
        $this->dispute = $dispute;
        $this->actor = $actor;
    }
}
