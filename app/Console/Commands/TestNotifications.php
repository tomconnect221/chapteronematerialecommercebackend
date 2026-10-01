<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Models\User;
use App\Models\Subadmin;
use Illuminate\Console\Command;

class TestNotifications extends Command
{
    protected $signature = 'test:notifications';
    protected $description = 'Test the notification system by sending sample notifications';

    public function handle(NotificationService $notificationService): int
    {
        $this->info('Testing notification system...');

        // Check if we have admin and subadmin users
        $adminCount = User::where('role', 'admin')->where('is_active', true)->count();
        $subadminCount = Subadmin::where('is_active', true)->count();

        if ($adminCount === 0 && $subadminCount === 0) {
            $this->error('No active admin or subadmin users found. Please create some users first.');
            return 1;
        }

        $this->info("Found {$adminCount} admin users and {$subadminCount} subadmin users");

        // Test order notification
        $this->info('Sending test order notification...');
        $notificationService->sendToAdmins(
            type: 'test_new_order',
            title: 'Test Order Notification',
            message: 'This is a test notification for new order functionality',
            data: [
                'test' => true,
                'order_id' => 'test-123',
                'customer_name' => 'Test Customer',
            ],
            options: [
                'category' => 'order',
                'priority' => 'high',
                'action_url' => '/admin/orders/test-123',
                'action_text' => 'View Order',
            ]
        );

        // Test inventory notification
        $this->info('Sending test inventory notification...');
        $notificationService->sendToAdmins(
            type: 'test_low_stock',
            title: 'Test Low Stock Alert',
            message: 'This is a test notification for low stock functionality',
            data: [
                'test' => true,
                'product_id' => 'test-product-123',
                'product_name' => 'Test Product',
                'available_quantity' => 5,
                'reorder_level' => 10,
            ],
            options: [
                'category' => 'inventory',
                'priority' => 'medium',
                'action_url' => '/subadmin/inventory/test-product-123',
                'action_text' => 'Restock Product',
            ]
        );

        // Test payment notification
        $this->info('Sending test payment notification...');
        $notificationService->sendToAdmins(
            type: 'test_payment_failed',
            title: 'Test Payment Failed Alert',
            message: 'This is a test notification for payment failure functionality',
            data: [
                'test' => true,
                'order_id' => 'test-order-456',
                'failure_reason' => 'Test failure reason',
            ],
            options: [
                'category' => 'payment',
                'priority' => 'critical',
                'action_url' => '/admin/orders/test-order-456',
                'action_text' => 'Investigate Payment',
            ]
        );

        $this->info('Test notifications sent successfully!');
        $this->info('Check your admin/subadmin dashboard to see the notifications.');

        return 0;
    }
}