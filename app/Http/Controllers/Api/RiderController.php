<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfilePictureRequest;
use App\Mail\OrderStatusChangeEmail;
use App\Models\Order;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RiderController extends Controller
{
    /**
     * Update rider status
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:available,busy,offline'
        ]);

        $rider = $request->user();
        $rider->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $rider
        ]);
    }

    /**
     * Update rider location
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180'
        ]);

        $rider = $request->user();
        $rider->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => $rider
        ]);
    }

    /**
     * Get rider's assigned orders
     */
    public function getOrders(Request $request): JsonResponse
    {
        $rider = $request->user();
        $orders = Order::where('rider_id', $rider->id)
            ->with(['customer', 'branch', 'orderItems.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Get order by ID
     */
    public function getOrder(Request $request, string $id): JsonResponse
    {
        $rider = $request->user();
        $order = Order::where('rider_id', $rider->id)
            ->with(['customer', 'branch', 'orderItems.product'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Mark order as delivered
     */
    public function markDelivered(Request $request, string $id): JsonResponse
    {
        $rider = $request->user();
        $order = Order::where('rider_id', $rider->id)->findOrFail($id);

        $oldStatus = $order->status;

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);

        // Make rider available again
        $rider->update(['status' => 'available']);

        // Load relationships for email
        $order->load(['customer', 'branch', 'rider', 'orderItems.product']);

        // Send order status change email (delivered) to customer
        if ($order->customer) {
            try {
                app(MailConfigService::class)->loadFromDatabase();
                Mail::to($order->customer->email)->send(new OrderStatusChangeEmail($order, $oldStatus, 'delivered'));
            } catch (\Exception $e) {
                Log::warning('Order delivered email failed to send', [
                    'order_id' => $order->id,
                    'customer_email' => $order->customer->email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail delivery update if email fails
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Order marked as delivered',
            'data' => $order->load(['customer', 'branch', 'orderItems.product'])
        ]);
    }

    /**
     * Get available orders for assignment
     */
    public function getAvailableOrders(): JsonResponse
    {
        $orders = Order::where('status', 'ready_for_delivery')
            ->whereNull('rider_id')
            ->with(['customer', 'branch', 'orderItems.product'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Update rider profile picture
     */
    public function updateProfilePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        $rider = $request->user();
        
        if (!$rider) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            // Delete old profile picture if it exists
            if (!empty($rider->profile_picture)) {
                $oldPath = str_replace('/storage/', '', $rider->profile_picture);
                Storage::disk('public')->delete($oldPath);
            }

            // Handle new profile picture upload
            $file = $request->file('profile_picture');
            $extension = $file->getClientOriginalExtension();
            $filename = $rider->id . '.' . $extension;
            $path = $file->storeAs('profiles/riders', $filename, 'public');
            
            // Update rider profile picture
            $rider->update([
                'profile_picture' => Storage::url($path)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'data' => [
                    'profile_picture' => $rider->profile_picture
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Rider profile picture update failed', [
                'rider_id' => $rider->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile picture. Please try again.',
            ], 500);
        }
    }

    /**
     * Get the authenticated rider's profile.
     */
    public function getProfile(Request $request): JsonResponse
    {
        $rider = $request->user();

        if (!$rider) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $rider,
        ]);
    }

    /**
     * Update the authenticated rider's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\Rider $rider */
        $rider = $request->user();

        if (!$rider) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:riders,email,' . $rider->id,
            'phone' => 'sometimes|string|max:20',
            'license_number' => 'sometimes|string|max:50',
            'vehicle_type' => 'sometimes|string|max:50',
            'vehicle_number' => 'sometimes|string|max:50',
            'current_password' => 'required_with:password|string',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $rider->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 422);
            }
            $data['password'] = Hash::make($data['password']);
        }
        unset($data['current_password'], $data['password_confirmation']);

        try {
            $rider->update($data);
            $rider->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $rider,
            ]);
        } catch (\Exception $e) {
            Log::error('Rider profile update failed', [
                'rider_id' => $rider->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile. Please try again.',
            ], 500);
        }
    }
}
