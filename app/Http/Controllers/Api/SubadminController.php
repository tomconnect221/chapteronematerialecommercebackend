<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProfilePictureRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Mail\OrderStatusChangeEmail;
use App\Mail\RiderAssignmentEmail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Services\ProductVariantService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubadminController extends Controller
{
    /**
     * Get subadmin profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\Subadmin $subadmin */
        $subadmin = $request->user();

        if (!$subadmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $subadmin,
        ]);
    }

    /**
     * Update subadmin profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\Subadmin $subadmin */
        $subadmin = $request->user();

        if (!$subadmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:subadmins,email,' . $subadmin->id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|nullable',
            'city' => 'sometimes|string|max:100|nullable',
            'state' => 'sometimes|string|max:100|nullable',
            'bio' => 'sometimes|string|nullable',
            'postal_code' => 'sometimes|string|max:20|nullable',
        ]);

        $subadmin->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $subadmin->fresh(),
        ]);
    }
    /**
     * Get subadmin's branch details
     */
    public function getBranch(Request $request): JsonResponse
    {
        $subadmin = $request->user('subadmin-api');

        if (!$subadmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or invalid token.',
            ], 401);
        }

        $branch = $subadmin->branch;

        return response()->json([
            'success' => true,
            'data' => $branch,
        ]);
    }

    /**
     * Update branch details
     */
    public function updateBranch(Request $request): JsonResponse
    {
        $subadmin = $request->user();
        $branch = $subadmin->branch;

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ]);

        $branch->update($request->only([
            'name',
            'address',
            'phone',
            'email',
            'latitude',
            'longitude',
            'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully',
            'data' => $branch
        ]);
    }

    /**
     * Get all products for subadmin's branch
     */
    public function getProducts(Request $request): JsonResponse
    {
        $subadmin = $request->user();

        $query = Product::where('branch_id', $subadmin->branch_id)
            ->with(['category', 'variants']);

        // 🔹 Category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // 🔹 Search by name, description, SKU, or category name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($cat) use ($search) {
                        $cat->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // 🔹 Price range filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // 🔹 Created date range
        if ($request->filled('from_date')) {
            try {
                $fromDate = Carbon::parse($request->from_date)->startOfDay();
                $query->whereDate('created_at', '>=', $fromDate);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid from_date format. Use YYYY-MM-DD or a valid date string.'
                ], 422);
            }
        }

        if ($request->filled('to_date')) {
            try {
                $toDate = Carbon::parse($request->to_date)->endOfDay();
                $query->whereDate('created_at', '<=', $toDate);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid to_date format. Use YYYY-MM-DD or a valid date string.'
                ], 422);
            }
        }

        // 🔹 Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        // 🔹 Sorting (default: latest)
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // 🔹 Paginate
        $products = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get product by ID
     */
    public function getProduct(Request $request, string $id): JsonResponse
    {
        $subadmin = $request->user();
        $product = Product::where('branch_id', $subadmin->branch_id)
            ->with(['category', 'variants'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Create a new product for subadmin's branch
     */
    public function createProduct(CreateProductRequest $request): JsonResponse
    {
        $subadmin = $request->user();
        $data = $request->validated();
        $variants = $data['variants'] ?? null;
        unset($data['variants']);
        $data['branch_id'] = $subadmin->branch_id;

        // Handle file uploads
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $uniqueName = Str::uuid()->toString() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('products', $uniqueName, 'public');
            $data['image'] = Storage::url($path);
        }

        if ($request->hasFile('images')) {
            $storedImages = [];
            foreach ($request->file('images') as $file) {
                $uniqueName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('products', $uniqueName, 'public');
                $storedImages[] = Storage::url($path);
            }
            $data['images'] = $storedImages;
        }

        DB::beginTransaction();
        
        try {
            $product = Product::create($data);

            if ($variants !== null) {
                app(ProductVariantService::class)->sync($product, $variants);
                $product->refresh();
            }
            
            // Automatically create inventory record for the new product
            \App\Models\Inventory::create([
                'product_id' => $product->id,
                'quantity_in_stock' => $product->stock_quantity ?? 0, // Use product's stock quantity
                'quantity_reserved' => 0,
                'reorder_level' => $product->min_stock_level ?? 10, // Use product's min stock level
                'max_stock_level' => null,
                'cost_price' => $product->cost_price, // Use product's cost price
                'selling_price' => $product->price, // Use product price as default
                'supplier' => null,
                'is_active' => true,
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Product created successfully with inventory tracking',
                'data' => $product->load(['category', 'inventory', 'variants'])
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Subadmin product creation failed', [
                'subadmin_id' => $subadmin->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product'
            ], 500);
        }
    }

    /**
     * Update product
     */
    public function updateProduct(UpdateProductRequest $request, string $id): JsonResponse
    {
        $subadmin = $request->user();
        $product = Product::where('branch_id', $subadmin->branch_id)->findOrFail($id);
        $data = $request->validated();
        $variants = $data['variants'] ?? null;
        unset($data['variants']);

        // Handle file uploads
        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if (!empty($product->image)) {
                $oldPath = str_replace('/storage/', '', $product->image);
                Storage::disk('public')->delete($oldPath);
            }

            // Store new image with unique name
            $image = $request->file('image');
            $uniqueName = Str::uuid()->toString() . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('products', $uniqueName, 'public');
            $data['image'] = Storage::url($path);
        }

        if ($request->hasFile('images')) {
            // Delete old images if they exist
            if (!empty($product->images) && is_array($product->images)) {
                foreach ($product->images as $oldImage) {
                    $oldPath = str_replace('/storage/', '', $oldImage);
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Upload and store new ones
            $storedImages = [];
            foreach ($request->file('images') as $file) {
                $uniqueName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('products', $uniqueName, 'public');
                $storedImages[] = Storage::url($path);
            }
            $data['images'] = $storedImages;
        }

        $product->update($data);

        if ($variants !== null) {
            app(ProductVariantService::class)->sync($product, $variants);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->fresh()->load(['category', 'variants'])
        ]);
    }

    /**
     * Delete product
     */
    public function deleteProduct(Request $request, string $id): JsonResponse
    {
        $subadmin = $request->user();
        $product = Product::where('branch_id', $subadmin->branch_id)->findOrFail($id);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Get all categories
     */
    public function getCategories(): JsonResponse
    {
        $categories = Category::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Create category
     */
    public function createCategory(CreateCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Update category
     */
    public function updateCategory(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Delete a category when the subadmin has been granted permission.
     */
    public function deleteCategory(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Get all orders for subadmin's branch
     */
    public function getOrders(Request $request): JsonResponse
    {
        $subadmin = $request->user();
        $query = Order::where('branch_id', $subadmin->branch_id)
            ->with(['customer', 'rider', 'orderItems.product']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

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
        $subadmin = $request->user();
        $order = Order::where('branch_id', $subadmin->branch_id)
            ->with(['customer', 'rider', 'orderItems.product.category', 'orderItems.variant'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        $subadmin = $request->user();
        $order = Order::where('branch_id', $subadmin->branch_id)->findOrFail($id);
        $oldStatus = $order->status;
        $order->update($request->validated());
        $newStatus = $order->status;

        // Load relationships
        $order->load(['customer', 'rider', 'orderItems.product']);

        // Send email notification to customer if status changed
        if ($oldStatus !== $newStatus && $order->customer) {
            try {
                Mail::to($order->customer->email)->send(new OrderStatusChangeEmail($order, $oldStatus, $newStatus));
            } catch (\Exception $e) {
                Log::warning('Order status change email failed to send', [
                    'order_id' => $order->id,
                    'customer_email' => $order->customer->email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail status update if email fails
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->load(['customer', 'rider', 'orderItems.product'])
        ]);
    }

    /**
     * Get available riders
     */
    public function getAvailableRiders(): JsonResponse
    {
        $riders = Rider::where('status', 'available')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $riders
        ]);
    }

    /**
     * Assign rider to order
     */
    public function assignRider(Request $request, string $orderId): JsonResponse
    {
        $subadmin = $request->user();
        $order = Order::where('branch_id', $subadmin->branch_id)->findOrFail($orderId);

        $request->validate([
            'rider_id' => 'required|uuid|exists:riders,id'
        ]);

        $rider = Rider::findOrFail($request->rider_id);

        if (!$rider->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Rider is not available'
            ], 400);
        }

        $order->update([
            'rider_id' => $request->rider_id,
            'status' => 'out_for_delivery'
        ]);

        $rider->update(['status' => 'busy']);

        // Load relationships
        $order->load(['customer', 'rider', 'orderItems.product']);

        // Send email notification to rider
        try {
            Mail::to($rider->email)->send(new RiderAssignmentEmail($order, $rider));
        } catch (\Exception $e) {
            Log::warning('Rider assignment email failed to send', [
                'order_id' => $order->id,
                'rider_email' => $rider->email,
                'error' => $e->getMessage()
            ]);
            // Don't fail assignment if email fails
        }

        // Also send status change email to customer
        if ($order->customer) {
            try {
                Mail::to($order->customer->email)->send(new OrderStatusChangeEmail($order, 'ready_for_delivery', 'out_for_delivery'));
            } catch (\Exception $e) {
                Log::warning('Order status change email failed to send', [
                    'order_id' => $order->id,
                    'customer_email' => $order->customer->email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rider assigned successfully',
            'data' => $order->load(['customer', 'rider', 'orderItems.product'])
        ]);
    }

    /**
     * Get branch statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $subadmin = $request->user();
        $branchId = $subadmin->branch_id;

        $stats = [
            'total_products' => Product::where('branch_id', $branchId)->count(),
            'total_orders' => Order::where('branch_id', $branchId)->count(),
            'pending_orders' => Order::where('branch_id', $branchId)->where('status', 'pending')->count(),
            'completed_orders' => Order::where('branch_id', $branchId)->where('status', 'delivered')->count(),
            'total_revenue' => Order::where('branch_id', $branchId)->where('status', 'delivered')->sum('total_amount'),
            'low_stock_products' => Product::where('branch_id', $branchId)->where('stock_quantity', '<', 10)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Update subadmin profile picture
     */
    public function updateProfilePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        /** @var \App\Models\Subadmin $subadmin */
        $subadmin = Auth::user();
        
        if (!$subadmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            // Delete old profile picture if it exists
            if (!empty($subadmin->profile_picture)) {
                $oldPath = str_replace('/storage/', '', $subadmin->profile_picture);
                Storage::disk('public')->delete($oldPath);
            }

            // Handle new profile picture upload
            $file = $request->file('profile_picture');
            $extension = $file->getClientOriginalExtension();
            $filename = $subadmin->id . '.' . $extension;
            $path = $file->storeAs('profiles/subadmins', $filename, 'public');
            
            // Update subadmin profile picture
            $subadmin->update([
                'profile_picture' => Storage::url($path)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'data' => [
                    'profile_picture' => $subadmin->profile_picture
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Subadmin profile picture update failed', [
                'subadmin_id' => $subadmin->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile picture. Please try again.',
            ], 500);
        }
    }
}
