<?php

namespace App\Orders\States;

class OrderStateFactory
{
    private const MAP = [
        'created'   => CreatedOrderState::class,
        'paid'      => PaidOrderState::class,
        'accepted'  => AcceptedOrderState::class,
        'preparing' => PreparingOrderState::class,
        'ready'     => ReadyOrderState::class,
        'picked_up' => PickedUpOrderState::class,
        'delivered' => DeliveredOrderState::class,
        'cancelled' => CancelledOrderState::class,
        'refunded'  => RefundedOrderState::class,
    ];

    public static function make(string $status): OrderState
    {
        if (!isset(self::MAP[$status])) {
            throw new \Exception("Unknown current status: {$status}");
        }

        $class = self::MAP[$status];

        return new $class();
    }
}
