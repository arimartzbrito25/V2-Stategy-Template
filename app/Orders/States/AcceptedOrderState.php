<?php

namespace App\Orders\States;

class AcceptedOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'accepted';
    }

    protected function allowedTransitions(): array
    {
        return ['preparing', 'cancelled'];
    }
}
