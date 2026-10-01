<?php

namespace App\Providers;

use App\Events\OrderCreated;
use App\Events\PaymentFailed;
use App\Events\InventoryLowStock;
use App\Listeners\SendOrderCreatedNotification;
use App\Listeners\SendPaymentFailedNotification;
use App\Listeners\SendInventoryLowStockNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        OrderCreated::class => [
            SendOrderCreatedNotification::class,
        ],
        PaymentFailed::class => [
            SendPaymentFailedNotification::class,
        ],
        InventoryLowStock::class => [
            SendInventoryLowStockNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}