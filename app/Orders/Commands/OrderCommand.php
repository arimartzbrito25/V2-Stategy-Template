<?php

namespace App\Orders\Commands;

interface OrderCommand
{
    public function execute(): void;
}
