<?php

namespace App\Payments;

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId,
        public readonly array $rawResponse,
    ) {}
}
