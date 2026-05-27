<?php

namespace App\Discounts;

use App\Models\Discount;
use App\Models\Order;

class FirstPurchaseDiscountStrategy implements DiscountStrategy
{
    public function calculate(Discount $discount, Order $order): float
    {
        $previousOrders = Order::where('customer_id', $order->customer_id)
            ->whereIn('status', ['delivered', 'paid', 'accepted', 'preparing', 'ready', 'picked_up'])
            ->when($order->id, fn ($query) => $query->where('id', '!=', $order->id))
            ->count();

        if ($previousOrders > 0) {
            return 0.0;
        }

        $amount = $order->subtotal * ($discount->value / 100);

        if ($discount->max_discount_amount !== null) {
            $amount = min($amount, $discount->max_discount_amount);
        }

        return (float) $amount;
    }
}
