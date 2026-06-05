<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Services\MetricsService;

class DispatchOrderSideEffects
{
    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order;
        $status = $event->newStatus;

        $inventoryService = new InventoryService();
        $auditService     = new AuditService();
        $metricsService   = new MetricsService();

        if ($status === 'created') {
            $inventoryService->reserveStock($order);
            $auditService->log('order.created', $order->id, $order->toArray());
            $metricsService->increment('orders.created');

        } elseif ($status === 'paid') {
            $auditService->log('order.paid', $order->id, ['amount' => $order->total]);
            $metricsService->increment('orders.paid');
            $metricsService->gauge('revenue', $order->total);

        } elseif ($status === 'accepted') {
            $auditService->log('order.accepted', $order->id);

        } elseif ($status === 'preparing') {
            $metricsService->timing('order.preparation_start', now()->timestamp);

        } elseif ($status === 'ready') {
            $metricsService->timing('order.ready', now()->timestamp);

        } elseif ($status === 'picked_up') {
            $auditService->log('order.picked_up', $order->id);

        } elseif ($status === 'delivered') {
            $inventoryService->confirmDelivery($order);
            $auditService->log('order.delivered', $order->id);
            $metricsService->increment('orders.delivered');

        } elseif ($status === 'cancelled') {
            $inventoryService->releaseStock($order);
            $auditService->log('order.cancelled', $order->id);
            $metricsService->increment('orders.cancelled');

        } elseif ($status === 'refunded') {
            $auditService->log('order.refunded', $order->id, ['amount' => $order->total]);
            $metricsService->increment('orders.refunded');
        }
    }
}
