<?php

namespace App\Discounts;

use App\Models\Discount;
use App\Models\Order;

class FreeDeliveryDiscountStrategy implements DiscountStrategy
{
    public function calculate(Discount $discount, Order $order): float
    {
        return (float) $order->delivery_fee;
    }
}
