<?php

namespace App\Payments;

interface PaymentGateway
{
    public function charge(int $orderId, float $amount, string $currency): PaymentResult;

    public function refund(string $transactionId, float $amount): PaymentResult;
}
