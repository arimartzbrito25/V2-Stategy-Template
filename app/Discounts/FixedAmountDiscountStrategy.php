<?php

namespace App\Discounts;

use App\Models\Discount;
use App\Models\Order;

class FixedAmountDiscountStrategy implements DiscountStrategy
{
    public function calculate(Discount $discount, Order $order): float
    {
        return (float) min($discount->value, $order->subtotal);
    }
}
