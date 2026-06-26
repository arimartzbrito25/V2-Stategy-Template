<?php

namespace App\Payments\Adapters;

use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;
use App\Services\Payments\BacTransferHandler;

class BacTransferAdapter implements PaymentGateway
{
    public function __construct(private BacTransferHandler $bac) {}

    public function charge(int $orderId, float $amount, string $currency): PaymentResult
    {
        $result = $this->bac->initiateTransfer($amount, $orderId);

        return new PaymentResult(
            $result['code'] === '00',
            $result['authorization'] ?? null,
            $result,
        );
    }

    public function refund(string $transactionId, float $amount): PaymentResult
    {
        $result = $this->bac->cancelTransfer($transactionId);

        return new PaymentResult(
            ($result['code'] ?? '') === '00',
            null,
            $result,
        );
    }
}
