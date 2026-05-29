<?php

namespace App\Orders\States;

class DeliveredOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'delivered';
    }

    protected function allowedTransitions(): array
    {
        return ['refunded', 'cancelled'];
    }
}
