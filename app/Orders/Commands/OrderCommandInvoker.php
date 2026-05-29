<?php

namespace App\Orders\Commands;

class OrderCommandInvoker
{
    public function run(OrderCommand $command): void
    {
        $command->execute();
    }
}
