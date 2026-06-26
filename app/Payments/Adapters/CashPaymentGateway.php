<?php

namespace App\Payments\Adapters;

use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;

class CashPaymentGateway implements PaymentGateway
{
    public function charge(int $orderId, float $amount, string $currency): PaymentResult
    {
        return new PaymentResult(true, null, ['type' => 'cash']);
    }

    public function refund(string $transactionId, float $amount): PaymentResult
    {
        return new PaymentResult(false, null, ['error' => 'Cash payments cannot be refunded online']);
    }
}
