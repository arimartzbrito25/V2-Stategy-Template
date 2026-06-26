<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentByCard;
use App\Models\PaymentInCash;
use App\Models\PaymentProvider;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\Vendor;
use App\Payments\PaymentGatewayFactory;
use App\Support\Logger;

class CheckoutFacade
{
    public function __construct(private Logger $logger) {}

    public function placeOrder(Customer $customer, array $cart, string $paymentMethod): Order
    {
        if (empty($cart['items'])) {
            throw new \Exception('Cart is empty.');
        }
        if (!$customer->verified) {
            throw new \Exception('Customer account is not verified.');
        }

        $vendor = Vendor::find($cart['vendor_id']);
        if (!$vendor || $vendor->status !== 'active') {
            throw new \Exception('Vendor is not available.');
        }

        $openingHours = $vendor->opening_hours ?? [];
        $dayKey = strtolower(now()->format('l'));
        if (!empty($openingHours[$dayKey])) {
            $hours = $openingHours[$dayKey];
            if ($hours['closed'] ?? false) {
                throw new \Exception('Vendor is currently closed.');
            }
        }

        $subtotal = 0.0;
        $orderItems = [];

        foreach ($cart['items'] as $item) {
            if ($item['type'] === 'product') {
                $product = Product::find($item['id']);
                if (!$product || !$product->available) {
                    throw new \Exception("Product {$item['id']} is not available.");
                }
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}.");
                }
                $unitPrice = $product->price;
                $orderItems[] = [
                    'item_type' => 'product',
                    'item_id'   => $product->id,
                    'quantity'  => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal'   => $unitPrice * $item['quantity'],
                ];
                $subtotal += $unitPrice * $item['quantity'];

            } elseif ($item['type'] === 'bundle') {
                $bundle = ProductBundle::find($item['id']);
                if (!$bundle || !$bundle->available) {
                    throw new \Exception("Bundle {$item['id']} is not available.");
                }
                $unitPrice = $bundle->getTotalPrice();
                $orderItems[] = [
                    'item_type' => 'bundle',
                    'item_id'   => $bundle->id,
                    'quantity'  => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal'   => $unitPrice * $item['quantity'],
                ];
                $subtotal += $unitPrice * $item['quantity'];
            }
        }

        if ($subtotal < 5.00) {
            throw new \Exception('Minimum order amount is $5.00.');
        }

        $deliveryFee = 2.50;
        if ($subtotal > 20.00) {
            $deliveryFee = 1.50;
        }
        if ($subtotal > 50.00) {
            $deliveryFee = 0.00;
        }

        $discountTotal = 0.0;
        $appliedDiscount = null;

        if (!empty($cart['discount_code'])) {
            $discount = Discount::where('code', $cart['discount_code'])->first();
            if ($discount) {
                $draftOrder = new Order([
                    'customer_id'  => $customer->id,
                    'vendor_id'    => $cart['vendor_id'],
                    'subtotal'     => $subtotal,
                    'delivery_fee' => $deliveryFee,
                ]);
                $draftOrder->setRelation(
                    'items',
                    collect($orderItems)->map(fn (array $row) => new OrderItem($row))
                );

                $discountTotal = $discount->apply($draftOrder);

                if ($discountTotal > 0) {
                    $appliedDiscount = $discount;
                    if ($discount->type === 'free_delivery') {
                        $deliveryFee = 0.00;
                    }
                }
            }
        }

        $total = $subtotal - $discountTotal + $deliveryFee;

        \DB::beginTransaction();
        try {
            $order = Order::create([
                'customer_id'      => $customer->id,
                'vendor_id'        => $cart['vendor_id'],
                'status'           => 'created',
                'subtotal'         => $subtotal,
                'discount_total'   => $discountTotal,
                'delivery_fee'     => $deliveryFee,
                'total'            => $total,
                'delivery_address' => $customer->address,
                'notes'            => $cart['notes'] ?? null,
            ]);

            foreach ($orderItems as $itemData) {
                OrderItem::create(array_merge($itemData, ['order_id' => $order->id]));
            }

            foreach ($cart['items'] as $item) {
                if ($item['type'] === 'product') {
                    $product = Product::find($item['id']);
                    $product->stock -= $item['quantity'];
                    if ($product->stock <= 0) {
                        $product->available = false;
                    }
                    $product->save();
                }
            }

            if ($appliedDiscount) {
                $appliedDiscount->current_uses++;
                $appliedDiscount->save();
                $order->discounts()->attach($appliedDiscount->id);
            }

            if ($paymentMethod === 'cash') {
                PaymentInCash::create([
                    'order_id'     => $order->id,
                    'provider_id'  => PaymentProvider::where('enabled', true)->orderBy('priority')->first()->id,
                    'amount'       => $total,
                    'currency'     => 'USD',
                    'status'       => 'completed',
                    'processed_at' => now(),
                ]);
            } else {
                $gateway = PaymentGatewayFactory::make('wompi');
                $result = $gateway->charge($order->id, $total, 'USD');

                PaymentByCard::create([
                    'order_id'                => $order->id,
                    'provider_id'             => PaymentProvider::where('name', 'Wompi')->first()->id,
                    'amount'                  => $total,
                    'currency'                => 'USD',
                    'status'                  => $result->success ? 'completed' : 'failed',
                    'external_transaction_id' => $result->transactionId,
                    'raw_response'            => $result->rawResponse,
                    'processed_at'            => $result->success ? now() : null,
                ]);

                if (!$result->success) {
                    throw new \Exception('Payment processing failed.');
                }
            }

            $order->status = 'paid';
            $order->save();

            $this->logger->log(
                "Order {$order->id} created by customer {$customer->id}. Total: {$total}"
            );

            $customer->loyalty_points += (int) floor($total);
            $customer->save();

            \DB::commit();
            return $order;

        } catch (\Exception $e) {
            \DB::rollBack();
            $this->logger->log(
                "Order creation failed for customer {$customer->id}: " . $e->getMessage(), 'error'
            );
            throw $e;
        }
    }
}
