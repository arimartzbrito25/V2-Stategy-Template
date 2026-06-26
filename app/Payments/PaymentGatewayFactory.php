<?php

namespace App\Payments;

use App\Payments\Adapters\BacTransferAdapter;
use App\Payments\Adapters\CashPaymentGateway;
use App\Payments\Adapters\N1coAdapter;
use App\Payments\Adapters\WompiAdapter;
use App\Services\Payments\BacTransferHandler;
use App\Services\Payments\N1coHandler;
use App\Services\Payments\WompiHandler;

class PaymentGatewayFactory
{
    public static function make(string $provider): PaymentGateway
    {
        return match ($provider) {
            'wompi' => new WompiAdapter(new WompiHandler()),
            'n1co' => new N1coAdapter(new N1coHandler()),
            'bac_transfer' => new BacTransferAdapter(new BacTransferHandler()),
            'cash' => new CashPaymentGateway(),
            default => throw new \InvalidArgumentException("Unknown payment provider: {$provider}"),
        };
    }
}
