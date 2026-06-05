<?php

namespace App\Providers;

use App\Events\OrderStatusChanged;
use App\Listeners\DispatchOrderSideEffects;
use App\Listeners\SendOrderNotifications;
use App\Support\Logger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Logger::class, fn () => new Logger());
    }

    public function boot(): void
    {
        Event::listen(OrderStatusChanged::class, SendOrderNotifications::class);
        Event::listen(OrderStatusChanged::class, DispatchOrderSideEffects::class);
    }
}
