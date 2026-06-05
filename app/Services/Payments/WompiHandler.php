<?php
namespace App\Services\Payments;

class WompiHandler
{
    private string $apiKey;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey   = config('services.wompi.api_key', '');
        $this->endpoint = config('services.wompi.endpoint', 'https://sandbox.wompi.co/v1');
    }

    // Nombre en español, parámetros distintos a N1co
    public function cobrar(float $monto, string $moneda, array $datos): array
    {
        app(\App\Support\Logger::class)->log("Wompi: cobrar {$monto} {$moneda}");
        return [
            'id_transaccion' => 'WOMPI-' . strtoupper(uniqid()),
            'estado'         => 'APROBADO',
            'monto'          => $monto,
            'moneda'         => $moneda,
            'referencia'     => $datos['referencia'] ?? '',
        ];
    }

    public function reembolsar(string $idTransaccion, float $monto): array
    {
        return ['id_reembolso' => 'WOMPI-REF-' . uniqid(), 'estado' => 'PROCESADO'];
    }

    public function consultarEstado(string $idTransaccion): array
    {
        return ['id_transaccion' => $idTransaccion, 'estado' => 'APROBADO'];
    }
}
