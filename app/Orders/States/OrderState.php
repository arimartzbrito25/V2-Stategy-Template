<?php

namespace App\Orders\States;

use App\Models\Order;

interface OrderState
{
    public function transitionTo(Order $order, string $newStatus): void;

    public function getName(): string;
}
