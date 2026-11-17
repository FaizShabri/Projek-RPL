<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public array $reviews;
    public User $customer;

    public function __construct(Order $order, array $reviews, User $customer)
    {
        $this->order = $order;
        $this->reviews = $reviews;
        $this->customer = $customer;
    }
}
