<?php

namespace App\Orders\States;

class RefundedOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'refunded';
    }

    protected function allowedTransitions(): array
    {
        return [];
    }
}
