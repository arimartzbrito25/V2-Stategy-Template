<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\EmailService;
use App\Services\PushService;
use App\Services\SMSService;
use App\Support\Logger;

class SendOrderNotifications
{
    public function __construct(private Logger $logger) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing(['customer.user', 'vendor.user', 'courier.user']);
        $status = $event->newStatus;

        $emailService = new EmailService();
        $smsService   = new SMSService();
        $pushService  = new PushService();

        if ($status === 'created') {
            $emailService->send($order->customer->user->email, 'Pedido recibido',
                "Tu pedido #{$order->id} ha sido recibido.");
            $emailService->send($order->vendor->user->email, 'Nuevo pedido',
                "Tienes un nuevo pedido #{$order->id}.");
            $smsService->send($order->customer->user->phone ?? '',
                "Pedido #{$order->id} confirmado.");

        } elseif ($status === 'paid') {
            $emailService->send($order->customer->user->email, 'Pago confirmado',
                "Tu pago para el pedido #{$order->id} fue procesado.");
            $pushService->send($order->customer->user->id, 'Pago recibido',
                "Tu pago fue procesado exitosamente.");

        } elseif ($status === 'accepted') {
            $emailService->send($order->customer->user->email, 'Pedido aceptado',
                "Tu pedido #{$order->id} está siendo preparado.");
            $pushService->send($order->customer->user->id, 'Pedido aceptado',
                "El restaurante aceptó tu pedido.");

        } elseif ($status === 'preparing') {
            $pushService->send($order->customer->user->id, 'Preparando tu pedido',
                "Tu comida está siendo preparada.");

        } elseif ($status === 'ready') {
            if ($order->courier) {
                $pushService->send($order->courier->user->id, 'Pedido listo para recoger',
                    "El pedido #{$order->id} está listo.");
            }

        } elseif ($status === 'picked_up') {
            $pushService->send($order->customer->user->id, 'Pedido en camino',
                "¡Tu pedido está en camino!");
            $smsService->send($order->customer->user->phone ?? '',
                "Tu pedido #{$order->id} está en camino.");

        } elseif ($status === 'delivered') {
            $emailService->send($order->customer->user->email, 'Pedido entregado',
                "Tu pedido #{$order->id} fue entregado. ¡Buen provecho!");
            $pushService->send($order->customer->user->id, '¡Pedido entregado!',
                "¡Disfruta tu pedido!");

        } elseif ($status === 'cancelled') {
            $emailService->send($order->customer->user->email, 'Pedido cancelado',
                "Tu pedido #{$order->id} fue cancelado.");

        } elseif ($status === 'refunded') {
            $emailService->send($order->customer->user->email, 'Reembolso procesado',
                "El reembolso de tu pedido #{$order->id} fue procesado.");
        }

        $this->logger->log("Order {$order->id} event dispatched: {$status}");
    }
}
