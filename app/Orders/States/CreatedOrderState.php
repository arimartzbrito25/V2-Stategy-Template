<?php

namespace App\Orders\States;

class CreatedOrderState extends AbstractOrderState
{
    public function getName(): string
    {
        return 'created';
    }

    protected function allowedTransitions(): array
    {
        return ['paid', 'cancelled'];
    }
}
