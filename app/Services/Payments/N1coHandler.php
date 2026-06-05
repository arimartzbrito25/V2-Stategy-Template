<?php
namespace App\Services\Payments;

class N1coHandler
{
    private string $apiKey;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey   = config('services.n1co.api_key', '');
        $this->endpoint = config('services.n1co.endpoint', 'https://sandbox.n1co.com/v2');
    }

    public function makePayment(array $data): array
    {
        app(\App\Support\Logger::class)->log("N1co: makePayment " . ($data['amount'] / 100));
        return [
            'payment_id' => 'N1CO-' . strtoupper(uniqid()),
            'status'     => 'success',
            'amount'     => $data['amount'],
            'currency'   => $data['currency'],
            'order_ref'  => $data['order_ref'],
        ];
    }

    public function reversePayment(string $paymentId, int $amountCents): array
    {
        return ['reversal_id' => 'N1CO-REV-' . uniqid(), 'status' => 'reversed'];
    }

    public function getPaymentStatus(string $paymentId): array
    {
        return ['payment_id' => $paymentId, 'status' => 'success'];
    }
}
