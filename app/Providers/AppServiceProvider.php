<?php

namespace App\Providers;

use App\Events\OrderStatusChanged;
use App\Listeners\DispatchOrderSideEffects;
use App\Listeners\SendOrderNotifications;
use App\Payments\Adapters\BacTransferAdapter;
use App\Payments\Adapters\CashPaymentGateway;
use App\Payments\Adapters\N1coAdapter;
use App\Payments\Adapters\WompiAdapter;
use App\Payments\PaymentGateway;
use App\Services\CheckoutFacade;
use App\Services\Payments\BacTransferHandler;
use App\Services\Payments\N1coHandler;
use App\Services\Payments\WompiHandler;
use App\Support\Logger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Logger::class, fn () => new Logger());
        $this->app->singleton(CheckoutFacade::class);

        $this->app->bind(PaymentGateway::class, function () {
            $provider = config('services.payment.default', 'wompi');

            return match ($provider) {
                'wompi' => new WompiAdapter(new WompiHandler()),
                'n1co' => new N1coAdapter(new N1coHandler()),
                'bac_transfer' => new BacTransferAdapter(new BacTransferHandler()),
                'cash' => new CashPaymentGateway(),
                default => new WompiAdapter(new WompiHandler()),
            };
        });
    }

    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, SendOrderNotifications::class);
        Event::listen(OrderStatusChanged::class, DispatchOrderSideEffects::class);
    }
}
