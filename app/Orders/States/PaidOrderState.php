<?php

namespace App\Orders\States;

class PaidOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'paid';
    }

    protected function allowedTransitions(): array
    {
        return ['accepted', 'cancelled', 'refunded'];
    }
}
