<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get user notifications
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $this->getUserType($user);
        
        $query = Notification::where('user_id', $user->id)
            ->where('user_type', $userType);

        if ($request->has('unread_only') && $request->unread_only) {
            $query->where('is_read', false);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $userType = $this->getUserType($user);
        
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->where('user_type', $userType)
            ->firstOrFail();

        $notification->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $this->getUserType($user);
        
        $count = Notification::where('user_id', $user->id)
            ->where('user_type', $userType)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read"
        ]);
    }

    /**
     * Delete notification
     */
    public function deleteNotification(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $userType = $this->getUserType($user);
        
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->where('user_type', $userType)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Get notification count
     */
    public function getNotificationCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $userType = $this->getUserType($user);
        
        $count = Notification::where('user_id', $user->id)
            ->where('user_type', $userType)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count
            ]
        ]);
    }

    /**
     * Get user type from authenticated user
     */
    private function getUserType($user): string
    {
        if (method_exists($user, 'role')) {
            return $user->role;
        }
        
        // Fallback based on model type
        $modelClass = get_class($user);
        return match($modelClass) {
            'App\Models\Customer' => 'customer',
            'App\Models\Rider' => 'rider',
            'App\Models\Subadmin' => 'subadmin',
            default => 'admin'
        };
    }
}
