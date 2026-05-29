<?php

namespace App\Orders\States;

class ReadyOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'ready';
    }

    protected function allowedTransitions(): array
    {
        return ['picked_up'];
    }
}
