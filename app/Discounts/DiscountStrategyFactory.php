<?php

namespace App\Discounts;

use App\Models\Discount;
use App\Support\Logger;

class DiscountStrategyFactory
{
    private const MAP = [
        'percentage'      => PercentageDiscountStrategy::class,
        'fixed_amount'    => FixedAmountDiscountStrategy::class,
        'bogo'            => BogoDiscountStrategy::class,
        'first_purchase'  => FirstPurchaseDiscountStrategy::class,
        'free_delivery'   => FreeDeliveryDiscountStrategy::class,
    ];

    public static function make(string $type): ?DiscountStrategy
    {
        $class = self::MAP[$type] ?? null;

        if ($class === null) {
            Logger::getInstance()->log(
                "Unknown discount type '{$type}'", 'warning'
            );
            return null;
        }

        return new $class();
    }
}
