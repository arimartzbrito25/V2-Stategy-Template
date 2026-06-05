<?php
namespace App\Services;
use App\Models\Order;
class InventoryService
{
    public function reserveStock(Order $order): void
    {
        app(\App\Support\Logger::class)->log("Stock reserved for order {$order->id}");
    }
    public function releaseStock(Order $order): void
    {
        app(\App\Support\Logger::class)->log("Stock released for order {$order->id}");
    }
    public function confirmDelivery(Order $order): void
    {
        app(\App\Support\Logger::class)->log("Delivery confirmed for order {$order->id}");
    }
}
