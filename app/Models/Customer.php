<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'address', 'city', 'latitude', 'longitude',
                           'verified', 'preferred_payment_method', 'loyalty_points'];

    protected $casts = ['verified' => 'boolean'];

    public function user() { return $this->belongsTo(User::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function notifications() { return $this->hasMany(Notification::class, 'recipient_id')
        ->where('recipient_type', 'customer'); }

    public function placeOrder(array $cart, string $paymentMethod): Order
    {
        // --- Validación del customer ---
        if (empty($cart['items'])) {
            throw new \Exception('Cart is empty.');
        }
        if (!$this->verified) {
            throw new \Exception('Customer account is not verified.');
        }

        // --- Validación del vendor (debería estar en Vendor) ---
        $vendor = Vendor::find($cart['vendor_id']);
        if (!$vendor || $vendor->status !== 'active') {
            throw new \Exception('Vendor is not available.');
        }

        // Lógica de horario dentro de placeOrder (debería ser scope en Vendor)
        $openingHours = $vendor->opening_hours ?? [];
        $now = now();
        $dayKey = strtolower($now->format('l'));
        if (!empty($openingHours[$dayKey])) {
            $hours = $openingHours[$dayKey];
            if ($hours['closed'] ?? false) {
                throw new \Exception('Vendor is currently closed.');
            }
        }

        // --- Construcción de items y subtotal ---
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
                $unitPrice = $product->price; // float, posible error de redondeo
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

        // --- Cálculo de delivery fee (números mágicos, sin servicio) ---
        $deliveryFee = 2.50;
        if ($subtotal > 20.00) { $deliveryFee = 1.50; }
        if ($subtotal > 50.00) { $deliveryFee = 0.00; }

        // --- Aplicación de descuento (Strategy vía Discount::apply) ---
        $discountTotal = 0.0;
        $appliedDiscount = null;

        if (!empty($cart['discount_code'])) {
            $discount = Discount::where('code', $cart['discount_code'])->first();
            if ($discount) {
                $draftOrder = new Order([
                    'customer_id'  => $this->id,
                    'vendor_id'    => $cart['vendor_id'],
                    'subtotal'     => $subtotal,
                    'delivery_fee' => $deliveryFee,
                ]);
                $draftOrder->setRelation(
                    'items',
                    collect($orderItems)->map(fn (array $item) => new OrderItem($item))
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

        // --- Persistencia (mezclada con lógica de negocio) ---
        \DB::beginTransaction();
        try {
            $order = Order::create([
                'customer_id'      => $this->id,
                'vendor_id'        => $cart['vendor_id'],
                'status'           => 'created',
                'subtotal'         => $subtotal,
                'discount_total'   => $discountTotal,
                'delivery_fee'     => $deliveryFee,
                'total'            => $total,
                'delivery_address' => $this->address, // duplicación de datos
                'notes'            => $cart['notes'] ?? null,
            ]);

            foreach ($orderItems as $itemData) {
                OrderItem::create(array_merge($itemData, ['order_id' => $order->id]));
            }

            // Actualización de stock (inventory concern mezclado aquí)
            foreach ($cart['items'] as $item) {
                if ($item['type'] === 'product') {
                    $product = Product::find($item['id']);
                    $product->stock -= $item['quantity'];
                    if ($product->stock <= 0) {
                        $product->available = false; // desincronización intencional: stock=0 pero available ya era true
                    }
                    $product->save();
                }
            }

            if ($appliedDiscount) {
                $appliedDiscount->current_uses++;
                $appliedDiscount->save();
                $order->discounts()->attach($appliedDiscount->id);
            }

            // --- Pago (DIP violation: acoplamiento directo) ---
            if ($paymentMethod === 'cash') {
                $payment = PaymentInCash::create([
                    'order_id'    => $order->id,
                    'provider_id' => PaymentProvider::where('enabled', true)->orderBy('priority')->first()->id,
                    'amount'      => $total,
                    'currency'    => 'USD',
                    'status'      => 'completed',
                    'processed_at' => now(),
                ]);
            } else {
                $wompiHandler = new \App\Services\Payments\WompiHandler();
                $result = $wompiHandler->cobrar($total, 'USD', [
                    'referencia'  => "ORDER-{$order->id}",
                    'descripcion' => "Pago orden #{$order->id}",
                ]);
                $success = $result['estado'] === 'APROBADO';

                $payment = PaymentByCard::create([
                    'order_id'                => $order->id,
                    'provider_id'             => PaymentProvider::where('name', 'Wompi')->first()->id,
                    'amount'                  => $total,
                    'currency'                => 'USD',
                    'status'                  => $success ? 'completed' : 'failed',
                    'external_transaction_id' => $result['id_transaccion'] ?? null,
                    'raw_response'            => $result,
                    'processed_at'            => $success ? now() : null,
                ]);

                if (!$success) {
                    throw new \Exception('Payment processing failed.');
                }
            }

            $order->status = 'paid';
            $order->save();

            app(\App\Support\Logger::class)->log(
                "Order {$order->id} created by customer {$this->id}. Total: {$total}"
            );

            // Loyalty points (magic number, sin política)
            $this->loyalty_points += (int) floor($total);
            $this->save();

            \DB::commit();
            return $order;

        } catch (\Exception $e) {
            \DB::rollBack();
            app(\App\Support\Logger::class)->log(
                "Order creation failed for customer {$this->id}: " . $e->getMessage(), 'error'
            );
            throw $e;
        }
    }
}
