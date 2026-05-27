<?php

namespace App\Discounts;

use App\Models\Discount;
use App\Models\Order;

interface DiscountStrategy
{
    public function calculate(Discount $discount, Order $order): float;
}
