<?php

namespace App\Discounts;

use App\Models\Discount;
use App\Models\Order;

class PercentageDiscountStrategy implements DiscountStrategy
{
    public function calculate(Discount $discount, Order $order): float
    {
        $amount = $order->subtotal * ($discount->value / 100);

        if ($discount->max_discount_amount !== null) {
            $amount = min($amount, $discount->max_discount_amount);
        }

        return (float) $amount;
    }
}
