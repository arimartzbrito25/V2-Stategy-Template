<?php

namespace App\Payments\Adapters;

use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;
use App\Services\Payments\N1coHandler;

class N1coAdapter implements PaymentGateway
{
    public function __construct(private N1coHandler $n1co) {}

    public function charge(int $orderId, float $amount, string $currency): PaymentResult
    {
        $result = $this->n1co->makePayment([
            'amount'    => (int) ($amount * 100),
            'currency'  => $currency,
            'order_ref' => $orderId,
        ]);

        return new PaymentResult(
            $result['status'] === 'success',
            $result['payment_id'] ?? null,
            $result,
        );
    }

    public function refund(string $transactionId, float $amount): PaymentResult
    {
        $result = $this->n1co->reversePayment($transactionId, (int) ($amount * 100));

        return new PaymentResult(
            ($result['status'] ?? '') === 'reversed',
            $result['reversal_id'] ?? null,
            $result,
        );
    }
}
