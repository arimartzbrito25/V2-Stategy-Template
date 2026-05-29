<?php

namespace App\Orders\Commands;

use App\Models\Order;
use App\Support\Logger;

class TransitionOrderCommand implements OrderCommand
{
    public function __construct(
        private Order $order,
        private string $targetStatus,
    ) {}

    public function execute(): void
    {
        $this->order->transitionTo($this->targetStatus);
        $this->order->save();
        $this->order->notify($this->targetStatus);

        Logger::getInstance()->log(
            "Order {$this->order->id} status updated to {$this->targetStatus}"
        );
    }
}
