<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\Commands\OrderCommandInvoker;
use App\Orders\Commands\TransitionOrderCommand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private OrderCommandInvoker $commands) {}

    public function process(Request $request, Order $order): JsonResponse
    {
        $request->validate(['provider' => 'required|in:wompi,n1co,bac_transfer,cash']);
        $provider = $request->provider;

        try {
            $result = [];
            $success = false;
            $transactionId = null;

            if ($provider === 'wompi') {
                $handler = new \App\Services\Payments\WompiHandler();
                $result  = $handler->cobrar($order->total, 'USD', [
                    'referencia'  => "ORDER-{$order->id}",
                    'descripcion' => "Pago orden #{$order->id}",
                ]);
                $success       = $result['estado'] === 'APROBADO';
                $transactionId = $result['id_transaccion'] ?? null;

            } elseif ($provider === 'n1co') {
                $handler = new \App\Services\Payments\N1coHandler();
                $result  = $handler->makePayment([
                    'amount'    => (int) ($order->total * 100), // N1co usa centavos
                    'currency'  => 'USD',
                    'order_ref' => $order->id,
                ]);
                $success       = $result['status'] === 'success';
                $transactionId = $result['payment_id'] ?? null;

            } elseif ($provider === 'bac_transfer') {
                $handler = new \App\Services\Payments\BacTransferHandler();
                $result  = $handler->initiateTransfer($order->total, $order->id);
                $success       = $result['code'] === '00';
                $transactionId = $result['authorization'] ?? null;

            } elseif ($provider === 'cash') {
                $success       = true;
                $transactionId = null;
                $result        = ['type' => 'cash'];
            }

            $payment = $order->payment;
            if ($payment) {
                $payment->status                  = $success ? 'completed' : 'failed';
                $payment->external_transaction_id = $transactionId;
                $payment->raw_response            = $result;
                $payment->processed_at            = $success ? now() : null;
                $payment->save();
            }

            if ($success) {
                $this->commands->run(new TransitionOrderCommand($order, 'paid'));
            }

            \App\Support\Logger::getInstance()->log(
                "Payment " . ($success ? 'succeeded' : 'failed') . " for order {$order->id} via {$provider}"
            );

            return response()->json(['success' => $success, 'transaction_id' => $transactionId]);

        } catch (\Exception $e) {
            \App\Support\Logger::getInstance()->log(
                "Payment error order {$order->id}: " . $e->getMessage(), 'error'
            );
            return response()->json(['error' => 'Payment processing failed.'], 500);
        }
    }

    public function refund(Request $request, Payment $payment): JsonResponse
    {
        try {
            $result = $payment->refund();
            return response()->json(['success' => $result]);
        } catch (\RuntimeException $e) {
            // Esto se dispara cuando el payment es PaymentInCash
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
