<?php

namespace App\Models;

use App\Services\CheckoutFacade;
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
        return app(CheckoutFacade::class)->placeOrder($this, $cart, $paymentMethod);
    }
}
