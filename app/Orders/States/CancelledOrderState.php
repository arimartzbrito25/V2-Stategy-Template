<?php

namespace App\Orders\States;

class CancelledOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'cancelled';
    }

    protected function allowedTransitions(): array
    {
        return [];
    }
}
