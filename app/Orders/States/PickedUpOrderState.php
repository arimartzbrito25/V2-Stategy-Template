<?php

namespace App\Orders\States;

class PickedUpOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'picked_up';
    }

    protected function allowedTransitions(): array
    {
        return ['delivered'];
    }
}
