<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminNotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Check if user has admin/subadmin privileges
     */
    private function checkAdminPrivileges(): bool
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'customer';
        return in_array($userRole, ['admin', 'subadmin']);
    }

    /**
     * Get notifications for admin/subadmin
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkAdminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin or Subadmin privileges required.'
            ], 403);
        }

        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'customer';

            $query = Notification::where('user_id', $user->id)
                ->where('user_type', $userRole)
                ->orderBy('created_at', 'desc');

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter by priority
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            // Filter by read status
            if ($request->has('is_read')) {
                $query->where('is_read', $request->boolean('is_read'));
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Date range filter
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $notifications = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch admin notifications', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'customer';

            $count = Notification::where('user_id', $user->id)
                ->where('user_type', $userRole)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get unread notification count', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count'
            ], 500);
        }
    }

    /**
     * Get notifications by category
     */
    public function getByCategory(string $category): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'customer';

            $notifications = Notification::where('user_id', $user->id)
                ->where('user_type', $userRole)
                ->where('category', $category)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications by category', [
                'user_id' => Auth::id(),
                'category' => $category,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Get critical notifications (high/critical priority)
     */
    public function getCritical(): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'customer';

            $notifications = Notification::where('user_id', $user->id)
                ->where('user_type', $userRole)
                ->whereIn('priority', ['high', 'critical'])
                ->where('is_read', false)
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch critical notifications', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch critical notifications'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'customer';

            $success = $this->notificationService->markAsRead($id, $user->id, $userRole);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification marked as read'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Notification not found or already read'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'user_id' => Auth::id(),
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'customer';

            $count = $this->notificationService->markAllAsRead($user->id, $userRole);

            return response()->json([
                'success' => true,
                'message' => "Marked {$count} notifications as read"
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read'
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'customer';

            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->where('user_type', $userRole)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notification', [
                'user_id' => Auth::id(),
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification'
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            $userRole = $user->role ?? 'customer';

            $stats = [
                'total' => Notification::where('user_id', $user->id)
                    ->where('user_type', $userRole)
                    ->count(),
                'unread' => Notification::where('user_id', $user->id)
                    ->where('user_type', $userRole)
                    ->where('is_read', false)
                    ->count(),
                'critical' => Notification::where('user_id', $user->id)
                    ->where('user_type', $userRole)
                    ->where('priority', 'critical')
                    ->where('is_read', false)
                    ->count(),
                'high' => Notification::where('user_id', $user->id)
                    ->where('user_type', $userRole)
                    ->where('priority', 'high')
                    ->where('is_read', false)
                    ->count(),
                'by_category' => Notification::where('user_id', $user->id)
                    ->where('user_type', $userRole)
                    ->where('is_read', false)
                    ->selectRaw('category, count(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get notification stats', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get notification statistics'
            ], 500);
        }
    }

    /**
     * Clean up expired notifications (admin only)
     */
    public function cleanupExpired(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Only admin can cleanup expired notifications
            if (($user->role ?? 'customer') !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin privileges required'
                ], 403);
            }

            $count = $this->notificationService->cleanupExpiredNotifications();

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$count} expired notifications"
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup expired notifications', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup expired notifications'
            ], 500);
        }
    }
}