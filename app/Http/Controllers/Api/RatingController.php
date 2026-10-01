<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RatingController extends Controller
{
    /**
     * Check customer privileges
     */
    private function checkCustomerPrivileges(): bool
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'customer';
        return $userRole === 'customer';
    }

    /**
     * Rate a rider (Customer only)
     */
    public function rateRider(Request $request): JsonResponse
    {
        if (!$this->checkCustomerPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Customer privileges required.'
            ], 403);
        }

        $request->validate([
            'rider_id' => 'required|uuid|exists:riders,id',
            'order_id' => 'required|uuid|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        try {
            $customer = Auth::user();
            $riderId = $request->rider_id;
            $orderId = $request->order_id;

            // Verify the order belongs to the customer
            $order = Order::where('id', $orderId)
                ->where('customer_id', $customer->id)
                ->where('rider_id', $riderId)
                ->where('status', 'delivered')
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or not delivered by this rider'
                ], 404);
            }

            // Check if customer has already rated this rider for this order
            if (Rating::hasCustomerRatedRiderForOrder($customer->id, $riderId, $orderId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already rated this rider for this order'
                ], 422);
            }

            // Create the rating
            $rating = Rating::create([
                'rider_id' => $riderId,
                'customer_id' => $customer->id,
                'order_id' => $orderId,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rider rated successfully',
                'data' => $rating->load(['rider', 'order'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to rate rider', [
                'customer_id' => Auth::id(),
                'rider_id' => $request->rider_id,
                'order_id' => $request->order_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to rate rider'
            ], 500);
        }
    }

    /**
     * Get rider ratings (Public)
     */
    public function getRiderRatings(string $riderId, Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 20);
            
            $ratings = Rating::where('rider_id', $riderId)
                ->with(['customer:id,name', 'order:id,order_number'])
                ->orderBy('created_at', 'desc')
                ->paginate($limit);

            // Get rider statistics
            $averageRating = Rating::getAverageRatingForRider($riderId);
            $totalRatings = Rating::getTotalRatingsForRider($riderId);

            return response()->json([
                'success' => true,
                'data' => $ratings,
                'statistics' => [
                    'average_rating' => round($averageRating, 2),
                    'total_ratings' => $totalRatings,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch rider ratings', [
                'rider_id' => $riderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rider ratings'
            ], 500);
        }
    }

    /**
     * Get customer's ratings (Customer only)
     */
    public function getCustomerRatings(Request $request): JsonResponse
    {
        if (!$this->checkCustomerPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Customer privileges required.'
            ], 403);
        }

        try {
            $customer = Auth::user();
            $limit = $request->get('limit', 20);
            
            $ratings = Rating::where('customer_id', $customer->id)
                ->with(['rider:id,name', 'order:id,order_number'])
                ->orderBy('created_at', 'desc')
                ->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $ratings
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch customer ratings', [
                'customer_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your ratings'
            ], 500);
        }
    }

    /**
     * Update a rating (Customer only)
     */
    public function updateRating(Request $request, string $ratingId): JsonResponse
    {
        if (!$this->checkCustomerPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Customer privileges required.'
            ], 403);
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        try {
            $customer = Auth::user();
            
            $rating = Rating::where('id', $ratingId)
                ->where('customer_id', $customer->id)
                ->firstOrFail();

            $rating->update([
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rating updated successfully',
                'data' => $rating->load(['rider', 'order'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update rating', [
                'customer_id' => Auth::id(),
                'rating_id' => $ratingId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Rating not found or update failed'
            ], 404);
        }
    }

    /**
     * Delete a rating (Customer only)
     */
    public function deleteRating(string $ratingId): JsonResponse
    {
        if (!$this->checkCustomerPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Customer privileges required.'
            ], 403);
        }

        try {
            $customer = Auth::user();
            
            $rating = Rating::where('id', $ratingId)
                ->where('customer_id', $customer->id)
                ->firstOrFail();

            $rating->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rating deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete rating', [
                'customer_id' => Auth::id(),
                'rating_id' => $ratingId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Rating not found or delete failed'
            ], 404);
        }
    }

    /**
     * Get all ratings (Admin/Subadmin only)
     */
    public function getAllRatings(Request $request): JsonResponse
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'customer';

        if (!in_array($userRole, ['admin', 'subadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            $query = Rating::with(['rider:id,name', 'customer:id,name', 'order:id,order_number']);

            // Filter by rider
            if ($request->has('rider_id')) {
                $query->where('rider_id', $request->rider_id);
            }

            // Filter by rating
            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $ratings = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $ratings
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch all ratings', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ratings'
            ], 500);
        }
    }
}
