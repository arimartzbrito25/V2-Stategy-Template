<?php

namespace App\Orders\States;

use App\Models\Order;

abstract class AbstractOrderState implements OrderState
{
    abstract protected function allowedTransitions(): array;

    public function transitionTo(Order $order, string $newStatus): void
    {
        if (!in_array($newStatus, $this->allowedTransitions(), true)) {
            throw new \Exception(
                "Cannot transition from '{$this->getName()}' to '{$newStatus}'."
            );
        }

        $order->status = $newStatus;
    }
}
