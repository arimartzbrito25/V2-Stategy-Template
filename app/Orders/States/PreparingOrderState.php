<?php

namespace App\Orders\States;

class PreparingOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'preparing';
    }

    protected function allowedTransitions(): array
    {
        return ['ready'];
    }
}
