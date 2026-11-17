<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public OrderStatusHistory $statusHistory;
    public ?string $previousStatus;
    public ?User $actor;

    public function __construct(
        Order $order,
        OrderStatusHistory $statusHistory,
        ?string $previousStatus = null,
        ?User $actor = null
    ) {
        $this->order = $order;
        $this->statusHistory = $statusHistory;
        $this->previousStatus = $previousStatus;
        $this->actor = $actor;
    }
}
