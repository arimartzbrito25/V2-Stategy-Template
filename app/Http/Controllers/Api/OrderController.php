<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Customer;
use App\Orders\Commands\OrderCommandInvoker;
use App\Orders\Commands\TransitionOrderCommand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(private OrderCommandInvoker $commands) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'customer') {
            $orders = Order::where('customer_id', $user->customer->id)
                ->with(['vendor', 'items', 'payment'])
                ->orderByDesc('created_at')
                ->get();
        } elseif ($user->role === 'vendor') {
            $orders = Order::where('vendor_id', $user->vendor->id)
                ->with(['customer.user', 'items', 'payment'])
                ->orderByDesc('created_at')
                ->get();
        } elseif ($user->role === 'courier') {
            $orders = Order::where('courier_id', $user->courier->id)
                ->with(['customer.user', 'vendor', 'items'])
                ->orderByDesc('created_at')
                ->get();
        } elseif ($user->role === 'admin') {
            $orders = Order::with(['customer.user', 'vendor', 'courier', 'items', 'payment'])
                ->orderByDesc('created_at')
                ->paginate(20);
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'customer') {
            return response()->json(['error' => 'Only customers can place orders'], 403);
        }

        $validated = $request->validate([
            'vendor_id'        => 'required|exists:vendors,id',
            'items'            => 'required|array|min:1',
            'items.*.type'     => 'required|in:product,bundle',
            'items.*.id'       => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method'   => 'required|in:card,cash,wallet,transfer',
            'discount_code'    => 'nullable|string',
            'notes'            => 'nullable|string|max:500',
        ]);

        try {
            $customer = $user->customer;
            $order    = $customer->placeOrder($validated, $validated['payment_method']);

            return response()->json([
                'message' => 'Order placed successfully.',
                'order'   => $order->load(['items', 'payment', 'discounts']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $canView = ($user->role === 'admin')
            || ($user->role === 'customer' && $order->customer_id === $user->customer?->id)
            || ($user->role === 'vendor'   && $order->vendor_id === $user->vendor?->id)
            || ($user->role === 'courier'  && $order->courier_id === $user->courier?->id);

        if (!$canView) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($order->load(['customer.user', 'vendor', 'courier', 'items', 'payment', 'discounts']));
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate(['status' => 'required|string']);

        try {
            $this->commands->run(new TransitionOrderCommand($order, $request->status));

            return response()->json(['message' => 'Status updated.', 'order' => $order]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            $this->commands->run(new TransitionOrderCommand($order, 'cancelled'));

            return response()->json(['message' => 'Order cancelled.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function accept(Request $request, Order $order): JsonResponse
    {
        try {
            $this->commands->run(new TransitionOrderCommand($order, 'accepted'));

            return response()->json(['message' => 'Order accepted.']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
