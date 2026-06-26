<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\Commands\OrderCommandInvoker;
use App\Orders\Commands\TransitionOrderCommand;
use App\Payments\PaymentGatewayFactory;
use App\Support\Logger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private OrderCommandInvoker $commands,
        private Logger $logger,
    ) {}

    public function process(Request $request, Order $order): JsonResponse
    {
        $request->validate(['provider' => 'required|in:wompi,n1co,bac_transfer,cash']);
        $provider = $request->provider;

        try {
            $gateway = PaymentGatewayFactory::make($provider);
            $result = $gateway->charge($order->id, $order->total, 'USD');

            $payment = $order->payment;
            if ($payment) {
                $payment->status                  = $result->success ? 'completed' : 'failed';
                $payment->external_transaction_id = $result->transactionId;
                $payment->raw_response            = $result->rawResponse;
                $payment->processed_at            = $result->success ? now() : null;
                $payment->save();
            }

            if ($result->success) {
                $this->commands->run(new TransitionOrderCommand($order, 'paid'));
            }

            $this->logger->log(
                "Payment " . ($result->success ? 'succeeded' : 'failed') . " for order {$order->id} via {$provider}"
            );

            return response()->json([
                'success' => $result->success,
                'transaction_id' => $result->transactionId,
            ]);

        } catch (\Exception $e) {
            $this->logger->log(
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
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
