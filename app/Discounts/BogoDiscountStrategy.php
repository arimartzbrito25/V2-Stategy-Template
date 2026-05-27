<?php

namespace App\Discounts;

use App\Models\Discount;
use App\Models\Order;

class BogoDiscountStrategy implements DiscountStrategy
{
    public function calculate(Discount $discount, Order $order): float
    {
        $cheapestPrice = $order->items
            ->map(fn ($item) => $item->unit_price)
            ->sort()
            ->values()
            ->first();

        return (float) ($cheapestPrice ?? 0.0);
    }
}
