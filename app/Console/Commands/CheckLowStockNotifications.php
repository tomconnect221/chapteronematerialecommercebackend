<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckLowStockNotifications extends Command
{
    protected $signature = 'notifications:check-low-stock';
    protected $description = 'Check for low stock items and send notifications';

    public function handle(NotificationService $notificationService): int
    {
        $this->info('Checking for low stock items...');

        // Find all inventories that are low on stock
        $lowStockInventories = Inventory::with('product')
            ->where('is_active', true)
            ->whereRaw('quantity_in_stock - quantity_reserved <= reorder_level')
            ->get();

        if ($lowStockInventories->isEmpty()) {
            $this->info('No low stock items found.');
            return 0;
        }

        $this->info("Found {$lowStockInventories->count()} low stock items");

        // Group by severity
        $criticalItems = $lowStockInventories->filter(function ($inventory) {
            return $inventory->quantity_available <= 0;
        });

        $lowStockItems = $lowStockInventories->filter(function ($inventory) {
            return $inventory->quantity_available > 0;
        });

        // Send individual notifications for critical items (out of stock)
        foreach ($criticalItems as $inventory) {
            $this->info("Sending out of stock notification for: {$inventory->product->name}");
            $notificationService->notifyOutOfStock($inventory);
        }

        // Send individual notifications for low stock items
        foreach ($lowStockItems as $inventory) {
            $this->info("Sending low stock notification for: {$inventory->product->name}");
            $notificationService->notifyLowStock($inventory);
        }

        // Send bulk notification if there are many items
        if ($lowStockInventories->count() >= 5) {
            $this->info('Sending bulk low stock notification...');
            $notificationService->notifyBulkLowStock($lowStockInventories->pluck('id')->toArray());
        }

        $this->info("Notifications sent for {$lowStockInventories->count()} items");
        return 0;
    }
}