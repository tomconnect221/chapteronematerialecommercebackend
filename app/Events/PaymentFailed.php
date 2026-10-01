<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $reason;

    public function __construct(Order $order, string $reason = 'Unknown')
    {
        $this->order = $order;
        $this->reason = $reason;
    }
}