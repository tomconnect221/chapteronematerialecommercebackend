<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send notification to admin and subadmin users
     */
    public function sendToAdmins(string $type, string $title, string $message, array $data = [], array $options = []): void
    {
        try {
            $notificationData = [
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'category' => $options['category'] ?? 'general',
                'priority' => $options['priority'] ?? 'medium',
                'action_url' => $options['action_url'] ?? null,
                'action_text' => $options['action_text'] ?? null,
                'metadata' => $options['metadata'] ?? null,
                'expires_at' => $options['expires_at'] ?? null,
            ];

            // Send to admin users
            $this->sendToUserType('admin', $notificationData);
            
            // Send to subadmin users
            $this->sendToUserType('subadmin', $notificationData);

        } catch (\Exception $e) {
            Log::error('Failed to send admin notification', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification to specific user type
     */
    private function sendToUserType(string $userType, array $notificationData): void
    {
        // Get all active users of the specified type
        $users = $this->getUsersByType($userType);
        
        foreach ($users as $user) {
            Notification::create([
                ...$notificationData,
                'user_id' => $user->id,
                'user_type' => $userType,
            ]);
        }
    }

    /**
     * Get users by type
     */
    private function getUsersByType(string $userType): \Illuminate\Database\Eloquent\Collection
    {
        return match ($userType) {
            'admin' => \App\Models\User::where('role', 'admin')->where('is_active', true)->get(),
            'subadmin' => \App\Models\Subadmin::where('is_active', true)->get(),
            default => collect(),
        };
    }

    // ===========================
    // ORDER NOTIFICATIONS
    // ===========================

    /**
     * New Order Received
     */
    public function notifyNewOrder(Order $order): void
    {
        $this->sendToAdmins(
            type: 'new_order',
            title: 'New Order Received',
            message: "New order #{$order->order_number} received from {$order->customer->name}",
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer->name,
                'total_amount' => $order->total_amount,
                'items_count' => $order->orderItems->count(),
            ],
            options: [
                'category' => 'order',
                'priority' => 'high',
                'action_url' => "/admin/orders/{$order->id}",
                'action_text' => 'View Order',
                'metadata' => [
                    'order_status' => $order->status,
                    'payment_status' => $order->payment_status,
                ]
            ]
        );
    }

    /**
     * Failed Payment / Payment Gateway Issues
     */
    public function notifyPaymentFailed(Order $order, string $reason = null): void
    {
        $this->sendToAdmins(
            type: 'payment_failed',
            title: 'Payment Failed - Immediate Action Required',
            message: "Payment failed for order #{$order->order_number}. Reason: " . ($reason ?? 'Unknown'),
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer->name,
                'amount' => $order->total_amount,
                'failure_reason' => $reason,
            ],
            options: [
                'category' => 'payment',
                'priority' => 'critical',
                'action_url' => "/admin/orders/{$order->id}",
                'action_text' => 'Investigate Payment',
                'metadata' => [
                    'payment_method' => $order->payment_method ?? 'unknown',
                    'gateway_response' => $reason,
                ]
            ]
        );
    }

    /**
     * Order Awaiting Fulfillment
     */
    public function notifyOrderAwaitingFulfillment(Order $order): void
    {
        $this->sendToAdmins(
            type: 'order_awaiting_fulfillment',
            title: 'Order Awaiting Fulfillment',
            message: "Order #{$order->order_number} is paid and ready for packing and shipping",
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer->name,
                'total_amount' => $order->total_amount,
                'paid_at' => $order->paid_at,
            ],
            options: [
                'category' => 'order',
                'priority' => 'high',
                'action_url' => "/admin/orders/{$order->id}",
                'action_text' => 'Process Order',
                'metadata' => [
                    'order_status' => $order->status,
                    'payment_status' => $order->payment_status,
                ]
            ]
        );
    }

    /**
     * Order Return / Refund Requested
     */
    public function notifyOrderReturnRequest(Order $order, string $reason = null): void
    {
        $this->sendToAdmins(
            type: 'order_return_request',
            title: 'Return/Refund Request',
            message: "Return/refund requested for order #{$order->order_number}",
            data: [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer->name,
                'return_reason' => $reason,
                'original_amount' => $order->total_amount,
            ],
            options: [
                'category' => 'order',
                'priority' => 'high',
                'action_url' => "/admin/orders/{$order->id}",
                'action_text' => 'Process Return',
                'metadata' => [
                    'return_reason' => $reason,
                    'request_date' => now()->toDateString(),
                ]
            ]
        );
    }

    // ===========================
    // INVENTORY NOTIFICATIONS
    // ===========================

    /**
     * Low Stock Alert
     */
    public function notifyLowStock(Inventory $inventory): void
    {
        $product = $inventory->product;
        $availableQuantity = $inventory->quantity_available;
        $reorderLevel = $inventory->reorder_level;

        $this->sendToAdmins(
            type: 'low_stock',
            title: 'Low Stock Alert',
            message: "Product '{$product->name}' is running low. Available: {$availableQuantity}, Reorder level: {$reorderLevel}",
            data: [
                'inventory_id' => $inventory->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'available_quantity' => $availableQuantity,
                'reorder_level' => $reorderLevel,
                'quantity_in_stock' => $inventory->quantity_in_stock,
                'quantity_reserved' => $inventory->quantity_reserved,
            ],
            options: [
                'category' => 'inventory',
                'priority' => $availableQuantity <= ($reorderLevel / 2) ? 'high' : 'medium',
                'action_url' => "/subadmin/inventory/{$inventory->id}",
                'action_text' => 'Restock Product',
                'metadata' => [
                    'supplier' => $inventory->supplier,
                    'cost_price' => $inventory->cost_price,
                    'last_restocked' => $inventory->last_restocked,
                ]
            ]
        );
    }

    /**
     * Out of Stock Alert
     */
    public function notifyOutOfStock(Inventory $inventory): void
    {
        $product = $inventory->product;

        $this->sendToAdmins(
            type: 'out_of_stock',
            title: 'Out of Stock Alert - URGENT',
            message: "Product '{$product->name}' is now out of stock!",
            data: [
                'inventory_id' => $inventory->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity_in_stock' => $inventory->quantity_in_stock,
                'quantity_reserved' => $inventory->quantity_reserved,
                'quantity_available' => $inventory->quantity_available,
            ],
            options: [
                'category' => 'inventory',
                'priority' => 'critical',
                'action_url' => "/subadmin/inventory/{$inventory->id}",
                'action_text' => 'Restock Immediately',
                'metadata' => [
                    'supplier' => $inventory->supplier,
                    'cost_price' => $inventory->cost_price,
                    'selling_price' => $inventory->selling_price,
                ]
            ]
        );
    }

    /**
     * Product Back in Stock
     */
    public function notifyProductBackInStock(Inventory $inventory, int $previousQuantity = 0): void
    {
        $product = $inventory->product;
        $currentQuantity = $inventory->quantity_available;

        $this->sendToAdmins(
            type: 'product_back_in_stock',
            title: 'Product Back in Stock',
            message: "Product '{$product->name}' is back in stock! Available quantity: {$currentQuantity}",
            data: [
                'inventory_id' => $inventory->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'current_quantity' => $currentQuantity,
                'previous_quantity' => $previousQuantity,
                'quantity_added' => $currentQuantity - $previousQuantity,
            ],
            options: [
                'category' => 'inventory',
                'priority' => 'medium',
                'action_url' => "/subadmin/inventory/{$inventory->id}",
                'action_text' => 'View Inventory',
                'metadata' => [
                    'supplier' => $inventory->supplier,
                    'restocked_at' => $inventory->last_restocked,
                ]
            ]
        );
    }

    /**
     * Bulk low stock notification for multiple products
     */
    public function notifyBulkLowStock(array $inventoryIds): void
    {
        $inventories = Inventory::with('product')->whereIn('id', $inventoryIds)->get();
        
        if ($inventories->isEmpty()) {
            return;
        }

        $productNames = $inventories->pluck('product.name')->toArray();
        $productCount = count($productNames);

        $this->sendToAdmins(
            type: 'bulk_low_stock',
            title: 'Multiple Products Low on Stock',
            message: "{$productCount} products are running low on stock: " . implode(', ', array_slice($productNames, 0, 5)) . ($productCount > 5 ? ' and more...' : ''),
            data: [
                'inventory_ids' => $inventoryIds,
                'product_count' => $productCount,
                'product_names' => $productNames,
            ],
            options: [
                'category' => 'inventory',
                'priority' => 'high',
                'action_url' => '/subadmin/inventory/alerts/low-stock',
                'action_text' => 'View All Low Stock Items',
                'metadata' => [
                    'alert_type' => 'bulk',
                    'generated_at' => now()->toDateTimeString(),
                ]
            ]
        );
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId, string $userId, string $userType): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->where('user_type', $userType)
            ->first();

        if ($notification && !$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(string $userId, string $userType): int
    {
        return Notification::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Clean up expired notifications
     */
    public function cleanupExpiredNotifications(): int
    {
        return Notification::where('expires_at', '<', now())->delete();
    }
}