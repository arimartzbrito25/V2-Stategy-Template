<?php

namespace App\Payments\Adapters;

use App\Payments\PaymentGateway;
use App\Payments\PaymentResult;
use App\Services\Payments\WompiHandler;

class WompiAdapter implements PaymentGateway
{
    public function __construct(private WompiHandler $wompi) {}

    public function charge(int $orderId, float $amount, string $currency): PaymentResult
    {
        $result = $this->wompi->cobrar($amount, $currency, [
            'referencia'  => "ORDER-{$orderId}",
            'descripcion' => "Pago orden #{$orderId}",
        ]);

        return new PaymentResult(
            $result['estado'] === 'APROBADO',
            $result['id_transaccion'] ?? null,
            $result,
        );
    }

    public function refund(string $transactionId, float $amount): PaymentResult
    {
        $result = $this->wompi->reembolsar($transactionId, $amount);

        return new PaymentResult(
            ($result['estado'] ?? '') === 'PROCESADO',
            $result['id_reembolso'] ?? null,
            $result,
        );
    }
}
