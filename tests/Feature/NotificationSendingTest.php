<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\PaymentProvider;
use App\Models\Category;
use App\Support\Logger;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationSendingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_sends_notification(): void
    {
        $logger = app(Logger::class);
        $logger->clearLogs();

        PaymentProvider::create(['name' => 'Wompi', 'api_endpoint' => 'x', 'api_key' => 'k', 'enabled' => true, 'priority' => 1]);
        $vendorUser = User::create(['name' => 'V', 'email' => 'vn@t.dev', 'password' => 'Password1', 'role' => 'vendor']);
        $vendor = Vendor::create(['user_id' => $vendorUser->id, 'business_name' => 'V', 'address' => 'A', 'city' => 'B', 'status' => 'active']);
        $category = Category::create(['vendor_id' => $vendor->id, 'name' => 'Cat', 'slug' => 'cat', 'display_order' => 0]);
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'P', 'price' => 10.00, 'available' => true, 'stock' => 50]);
        $customerUser = User::create(['name' => 'C', 'email' => 'cn@t.dev', 'password' => 'Password1', 'role' => 'customer']);
        Customer::create(['user_id' => $customerUser->id, 'address' => 'Colonia Escalón, Dirección Larga', 'city' => 'San Salvador', 'verified' => true]);

        $token = $customerUser->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/orders', [
            'vendor_id'      => $vendor->id,
            'items'          => [['type' => 'product', 'id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $this->assertNotEmpty($logger->getLogs());
    }
}
