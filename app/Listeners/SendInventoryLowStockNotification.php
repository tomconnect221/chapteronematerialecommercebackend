<?php

namespace App\Listeners;

use App\Events\InventoryLowStock;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendInventoryLowStockNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function handle(InventoryLowStock $event): void
    {
        $inventory = $event->inventory;
        
        // Check if inventory is out of stock
        if ($inventory->quantity_available <= 0) {
            $this->notificationService->notifyOutOfStock($inventory);
        } else {
            // Check if inventory is low on stock
            $this->notificationService->notifyLowStock($inventory);
        }
    }
}