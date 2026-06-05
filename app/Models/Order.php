<?php

namespace App\Models;

use App\Events\OrderStatusChanged;
use App\Orders\States\OrderStateFactory;
use App\Support\Logger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['customer_id', 'vendor_id', 'courier_id', 'status',
                           'subtotal', 'discount_total', 'delivery_fee', 'total',
                           'delivery_address', 'notes'];

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            event(new OrderStatusChanged($order, null, $order->status));
        });

        static::updated(function (Order $order) {
            if ($order->wasChanged('status')) {
                event(new OrderStatusChanged(
                    $order,
                    $order->getOriginal('status'),
                    $order->status
                ));
            }
        });
    }

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
        if (!$this->customer) {
            throw new \Exception('Customer not found.');
        }
        if (!$this->customer->verified) {
            throw new \Exception('Customer account is not verified.');
        }

        if (!$this->vendor) {
            throw new \Exception('Vendor not found.');
        }
        if ($this->vendor->status !== 'active') {
            throw new \Exception('Vendor is not currently active.');
        }

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

        if (empty($this->delivery_address)) {
            throw new \Exception('Delivery address is required.');
        }
        if (strlen($this->delivery_address) < 10) {
            throw new \Exception('Delivery address is too short to be valid.');
        }

        if ($this->subtotal <= 0) {
            throw new \Exception('Order subtotal must be greater than zero.');
        }
        if ($this->total < 0) {
            throw new \Exception('Order total cannot be negative.');
        }
        if ($this->subtotal < 5.00) {
            throw new \Exception('Minimum order amount is $5.00.');
        }

        if ($this->payment && $this->payment->status === 'failed') {
            throw new \Exception('Associated payment has failed.');
        }

        foreach ($this->discounts as $discount) {
            if (now() > $discount->valid_to) {
                throw new \Exception("Discount '{$discount->code}' has expired.");
            }
            if ($discount->vendor_id && $discount->vendor_id !== $this->vendor_id) {
                throw new \Exception("Discount '{$discount->code}' is not valid for this vendor.");
            }
        }

        app(Logger::class)->log("Order {$this->id} validated successfully.");
        return true;
    }
}
