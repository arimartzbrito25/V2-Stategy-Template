<?php

namespace App\Models;

use App\Orders\States\OrderStateFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['customer_id', 'vendor_id', 'courier_id', 'status',
                           'subtotal', 'discount_total', 'delivery_fee', 'total',
                           'delivery_address', 'notes'];

    public function customer()   { return $this->belongsTo(Customer::class); }
    public function vendor()     { return $this->belongsTo(Vendor::class); }
    public function courier()    { return $this->belongsTo(Courier::class); }
    public function items()      { return $this->hasMany(OrderItem::class); }
    public function discounts()  { return $this->belongsToMany(Discount::class, 'order_discounts'); }
    public function payment()    { return $this->hasOne(Payment::class); }
    public function notifications() { return $this->hasMany(Notification::class, 'recipient_id')
        ->where('recipient_type', 'customer'); }

    public function transitionTo(string $newStatus): void
    {
        OrderStateFactory::make($this->status)->transitionTo($this, $newStatus);
    }

    public function validateOrder(): bool
    {
        // Validar customer
        if (!$this->customer) {
            throw new \Exception('Customer not found.');
        }
        if (!$this->customer->verified) {
            throw new \Exception('Customer account is not verified.');
        }

        // Validar vendor
        if (!$this->vendor) {
            throw new \Exception('Vendor not found.');
        }
        if ($this->vendor->status !== 'active') {
            throw new \Exception('Vendor is not currently active.');
        }

        // Validar items
        if (!$this->items || $this->items->isEmpty()) {
            throw new \Exception('Order has no items.');
        }

        foreach ($this->items as $item) {
            if ($item->item_type === 'product') {
                $product = Product::find($item->item_id);
                if (!$product) {
                    throw new \Exception("Product ID {$item->item_id} no longer exists.");
                }
                if (!$product->available) {
                    throw new \Exception("Product '{$product->name}' is no longer available.");
                }
                if ($product->stock < $item->quantity) {
                    throw new \Exception(
                        "Insufficient stock for '{$product->name}': {$product->stock} available, {$item->quantity} requested."
                    );
                }
            } elseif ($item->item_type === 'bundle') {
                $bundle = ProductBundle::find($item->item_id);
                if (!$bundle) {
                    throw new \Exception("Bundle ID {$item->item_id} no longer exists.");
                }
                if (!$bundle->available) {
                    throw new \Exception("Bundle '{$bundle->name}' is not available.");
                }
            }
        }

        // Validar dirección de entrega
        if (empty($this->delivery_address)) {
            throw new \Exception('Delivery address is required.');
        }
        if (strlen($this->delivery_address) < 10) {
            throw new \Exception('Delivery address is too short to be valid.');
        }

        // Validar montos
        if ($this->subtotal <= 0) {
            throw new \Exception('Order subtotal must be greater than zero.');
        }
        if ($this->total < 0) {
            throw new \Exception('Order total cannot be negative.');
        }
        if ($this->subtotal < 5.00) {
            throw new \Exception('Minimum order amount is $5.00.');
        }

        // Validar payment (si existe)
        if ($this->payment && $this->payment->status === 'failed') {
            throw new \Exception('Associated payment has failed.');
        }

        // Validar descuentos aplicados
        foreach ($this->discounts as $discount) {
            if (now() > $discount->valid_to) {
                throw new \Exception("Discount '{$discount->code}' has expired.");
            }
            if ($discount->vendor_id && $discount->vendor_id !== $this->vendor_id) {
                throw new \Exception("Discount '{$discount->code}' is not valid for this vendor.");
            }
        }

        \App\Support\Logger::getInstance()->log("Order {$this->id} validated successfully.");
        return true;
    }

    public function notify(string $event): void
    {
        $emailService = new \App\Services\EmailService();
        $smsService   = new \App\Services\SMSService();
        $pushService  = new \App\Services\PushService();

        if ($event === 'created') {
            $emailService->send($this->customer->user->email, 'Pedido recibido',
                "Tu pedido #{$this->id} ha sido recibido.");
            $emailService->send($this->vendor->user->email, 'Nuevo pedido',
                "Tienes un nuevo pedido #{$this->id}.");
            $smsService->send($this->customer->user->phone ?? '',
                "Pedido #{$this->id} confirmado.");

        } elseif ($event === 'paid') {
            $emailService->send($this->customer->user->email, 'Pago confirmado',
                "Tu pago para el pedido #{$this->id} fue procesado.");
            $pushService->send($this->customer->user->id, 'Pago recibido',
                "Tu pago fue procesado exitosamente.");

        } elseif ($event === 'accepted') {
            $emailService->send($this->customer->user->email, 'Pedido aceptado',
                "Tu pedido #{$this->id} está siendo preparado.");
            $pushService->send($this->customer->user->id, 'Pedido aceptado',
                "El restaurante aceptó tu pedido.");

        } elseif ($event === 'preparing') {
            $pushService->send($this->customer->user->id, 'Preparando tu pedido',
                "Tu comida está siendo preparada.");

        } elseif ($event === 'ready') {
            if ($this->courier) {
                $pushService->send($this->courier->user->id, 'Pedido listo para recoger',
                    "El pedido #{$this->id} está listo.");
            }

        } elseif ($event === 'picked_up') {
            $pushService->send($this->customer->user->id, 'Pedido en camino',
                "¡Tu pedido está en camino!");
            $smsService->send($this->customer->user->phone ?? '',
                "Tu pedido #{$this->id} está en camino.");

        } elseif ($event === 'delivered') {
            $emailService->send($this->customer->user->email, 'Pedido entregado',
                "Tu pedido #{$this->id} fue entregado. ¡Buen provecho!");
            $pushService->send($this->customer->user->id, '¡Pedido entregado!',
                "¡Disfruta tu pedido!");

        } elseif ($event === 'cancelled') {
            $emailService->send($this->customer->user->email, 'Pedido cancelado',
                "Tu pedido #{$this->id} fue cancelado.");

        } elseif ($event === 'refunded') {
            $emailService->send($this->customer->user->email, 'Reembolso procesado',
                "El reembolso de tu pedido #{$this->id} fue procesado.");
        }

        \App\Support\Logger::getInstance()->log("Order {$this->id} event dispatched: {$event}");
        $this->dispatchSideEffects($event);
    }

    public function dispatchSideEffects(string $event): void
    {
        $inventoryService = new \App\Services\InventoryService();
        $auditService     = new \App\Services\AuditService();
        $metricsService   = new \App\Services\MetricsService();

        if ($event === 'created') {
            $inventoryService->reserveStock($this);
            $auditService->log('order.created', $this->id, $this->toArray());
            $metricsService->increment('orders.created');

        } elseif ($event === 'paid') {
            $auditService->log('order.paid', $this->id, ['amount' => $this->total]);
            $metricsService->increment('orders.paid');
            $metricsService->gauge('revenue', $this->total);

        } elseif ($event === 'accepted') {
            $auditService->log('order.accepted', $this->id);

        } elseif ($event === 'preparing') {
            $metricsService->timing('order.preparation_start', now()->timestamp);

        } elseif ($event === 'ready') {
            $metricsService->timing('order.ready', now()->timestamp);

        } elseif ($event === 'picked_up') {
            $auditService->log('order.picked_up', $this->id);

        } elseif ($event === 'delivered') {
            $inventoryService->confirmDelivery($this);
            $auditService->log('order.delivered', $this->id);
            $metricsService->increment('orders.delivered');

        } elseif ($event === 'cancelled') {
            $inventoryService->releaseStock($this);
            $auditService->log('order.cancelled', $this->id);
            $metricsService->increment('orders.cancelled');

        } elseif ($event === 'refunded') {
            $auditService->log('order.refunded', $this->id, ['amount' => $this->total]);
            $metricsService->increment('orders.refunded');
        }
    }
}
