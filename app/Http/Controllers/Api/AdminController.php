<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Requests\CreateSubadminRequest;
use App\Http\Requests\CreateRiderRequest;
use App\Http\Requests\UpdateRiderRequest;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateProfilePictureRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Requests\UpdateSubadminRequest;
use App\Mail\OrderStatusChangeEmail;
use App\Mail\RiderAssignmentEmail;
use App\Services\ProductVariantService;
use App\Services\MailConfigService;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Subadmin;
use App\Models\Order;
use Illuminate\Support\Str;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\Slider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * Get admin profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update admin profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|nullable',
            'city' => 'sometimes|string|max:100|nullable',
            'state' => 'sometimes|string|max:100|nullable',
            'bio' => 'sometimes|string|nullable',
            'postal_code' => 'sometimes|string|max:20|nullable',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user->fresh(),
        ]);
    }
    /**
     * Create a new branch
     */
    public function createBranch(CreateBranchRequest $request): JsonResponse
    {
        $branch = Branch::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully',
            'data' => $branch
        ], 201);
    }

    /**
     * Get all branches
     */
    public function getBranches(Request $request): JsonResponse
    {
        $query = Branch::with(['subadmins', 'products']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $branches = $query->get();

        return response()->json([
            'success' => true,
            'data' => $branches
        ]);
    }

    /**
     * Get branch by ID
     */
    public function getBranch(string $id): JsonResponse
    {
        $branch = Branch::with(['subadmins', 'products', 'orders'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $branch
        ]);
    }

    /**
     * Update branch
     */
    public function updateBranch(UpdateBranchRequest $request, string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $branch->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully',
            'data' => $branch
        ]);
    }

    /**
     * Delete branch
     */
    public function deleteBranch(string $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully'
        ]);
    }

    /**
     * Create a new subadmin
     */
    public function createSubadmin(CreateSubadminRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['permissions'] = array_key_exists('permissions', $data)
            ? Subadmin::normalizePermissions($data['permissions'])
            : Subadmin::DEFAULT_PERMISSIONS;

        $subadmin = Subadmin::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Subadmin created successfully',
            'data' => $subadmin->load('branch')
        ], 201);
    }

    /**
     * Get all subadmins
     */
    public function getSubadmins(Request $request): JsonResponse
    {
        $query = Subadmin::with('branch');

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $subadmins = $query->get();

        return response()->json([
            'success' => true,
            'data' => $subadmins
        ]);
    }

    /**
     * Get subadmin by ID
     */
    public function getSubadmin(string $id): JsonResponse
    {
        $subadmin = Subadmin::with('branch')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $subadmin
        ]);
    }

    /**
     * Update subadmin
     */
    public function updateSubadmin(UpdateSubadminRequest $request, string $id): JsonResponse
    {
        // Retrieve subadmin or fail
        $subadmin = Subadmin::with('branch')->findOrFail($id);

        // Gather only fillable fields
        $data = $request->only(['name', 'email', 'branch_id', 'is_active', 'permissions']);

        if ($request->has('permissions')) {
            $data['permissions'] = Subadmin::normalizePermissions($data['permissions']);
        }

        // Conditionally hash password if provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Update subadmin
        $subadmin->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Subadmin updated successfully',
            'data' => $subadmin->refresh() // ensures the latest state is returned
        ]);
    }

    /**
     * Delete subadmin
     */
    public function deleteSubadmin(string $id): JsonResponse
    {
        $subadmin = Subadmin::findOrFail($id);
        $subadmin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subadmin deleted successfully'
        ]);
    }

    /**
     * Create a new rider
     */
    public function createRider(CreateRiderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $rider = Rider::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Rider created successfully',
            'data' => $rider
        ], 201);
    }

    /**
     * Get all riders
     */
    public function getRiders(Request $request): JsonResponse
    {
        $query = Rider::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $riders = $query->get();

        return response()->json([
            'success' => true,
            'data' => $riders
        ]);
    }

    /**
     * Get rider by ID
     */
    public function getRider(string $id): JsonResponse
    {
        $rider = Rider::with('orders')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $rider
        ]);
    }

    /**
     * Update rider
     */
    public function updateRider(UpdateRiderRequest $request, string $id): JsonResponse
    {
        $rider = Rider::findOrFail($id);
        $data = $request->validated();

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $rider->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Rider updated successfully',
            'data' => $rider
        ]);
    }

    /**
     * Delete rider
     */
    public function deleteRider(string $id): JsonResponse
    {
        $rider = Rider::findOrFail($id);
        $rider->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rider deleted successfully'
        ]);
    }

    /**
     * Create a new category
     */
    public function createCategory(CreateCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $image      = $request->file('image');
            $fileName   = uniqid() . '_' . time() . '.' . $image->getClientOriginalExtension();
            $path       = $image->storeAs('categories', $fileName, 'public');

            $data['image'] = $path;
        }

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data'    => $category
        ], 201);
    }

    /**
     * Get all categories
     */
    public function getCategories(Request $request): JsonResponse
    {
        $query = Category::with('products');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $categories = $query->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get category by ID
     */
    public function getCategory(string $id): JsonResponse
    {
        $category = Category::with('products')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Update category
     */
    public function updateCategory(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();

        // If no image uploaded, simply update and return
        if (! $request->hasFile('image')) {
            $category->update($data);
            $category->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully (no image change)',
                'data'    => $category,
            ]);
        }

        // Image replacement flow:
        // 1. store new file
        // 2. update DB to point at new file
        // 3. delete old file
        // Wrapped in DB transaction; if anything fails, remove newly stored file and rollback
        $image = $request->file('image');
        $filename = time() . '_' . Str::random(8) . '.' . $image->getClientOriginalExtension();
        $newPath = null;
        $oldPath = $category->image ?? null;

        try {
            DB::beginTransaction();

            // store new file on 'public' disk under categories/
            $newPath = $image->storeAs('categories', $filename, 'public');

            // merge image path into validated data and update model
            $data['image'] = $newPath;
            $category->update($data);

            // delete old file only after successful DB update
            if (! empty($oldPath) && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            DB::commit();

            $category->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully (image replaced)',
                'data'    => $category,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // cleanup: remove newly stored file if it exists
            if (! empty($newPath) && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            Log::error('Failed to update category ' . $id . ': ' . $e->getMessage(), [
                'exception' => $e,
                'category_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update category (see logs).',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete category
     */
    public function deleteCategory(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Create a new product
     */
    public function createProduct(CreateProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $variants = $data['variants'] ?? null;
        unset($data['variants']);

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
                'data' => $product->load(['category', 'branch', 'inventory', 'variants']),
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Product creation failed', [
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
     * Get all products
     */
    public function getProducts(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'branch', 'variants']);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get product by ID
     */
    public function getProduct(string $id): JsonResponse
    {
        $product = Product::with(['category', 'branch', 'orderItems', 'variants'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Update product
     */
    public function updateProduct(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validated();
        $variants = $data['variants'] ?? null;
        $hasExistingImageList = array_key_exists('existing_images', $data);
        $existingImageList = $data['existing_images'] ?? [];
        unset($data['variants']);
        unset($data['existing_images']);

        // === Handle single main image replacement ===
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

        // === Reconcile saved images and append new uploads ===
        if ($hasExistingImageList || $request->hasFile('images')) {
            $oldImages = is_array($product->images) ? $product->images : [];
            $retainedImages = $hasExistingImageList
                ? array_values(array_intersect($oldImages, $existingImageList))
                : [];

            foreach ($oldImages as $oldImage) {
                if (!in_array($oldImage, $retainedImages, true)) {
                    $oldPath = str_replace('/storage/', '', $oldImage);
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $storedImages = [];
            foreach ($request->file('images', []) as $file) {
                $uniqueName = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('products', $uniqueName, 'public');
                $storedImages[] = Storage::url($path);
            }
            $data['images'] = array_merge($retainedImages, $storedImages);
        }

        // === Update Product ===
        $product->update($data);

        if ($variants !== null) {
            app(ProductVariantService::class)->sync($product, $variants);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->fresh()->load(['category', 'branch', 'variants']),
        ]);
    }

    /**
     * Delete product
     */
    public function deleteProduct(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Get all orders
     */
    public function getOrders(Request $request): JsonResponse
    {
        $query = Order::with(['customer', 'branch', 'rider', 'orderItems.product']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
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
    public function getOrder(string $id): JsonResponse
    {
        $order = Order::with(['customer', 'branch', 'rider', 'orderItems.product'])->findOrFail($id);

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
        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        $order->update($request->validated());
        $newStatus = $order->status;

        // Load relationships
        $order->load(['customer', 'branch', 'rider', 'orderItems.product']);

        // Send email notification to customer if status changed
        if ($oldStatus !== $newStatus && $order->customer) {
            try {
                app(MailConfigService::class)->loadFromDatabase();
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
            'data' => $order->load(['customer', 'branch', 'rider', 'orderItems.product'])
        ]);
    }

    /**
     * Assign rider to order
     */
    public function assignRider(Request $request, string $orderId): JsonResponse
    {
        $request->validate([
            'rider_id' => 'required|uuid|exists:riders,id'
        ]);

        $order = Order::findOrFail($orderId);
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
        $order->load(['customer', 'branch', 'rider', 'orderItems.product']);

        // Send email notification to rider
        try {
            app(MailConfigService::class)->loadFromDatabase();
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
                app(MailConfigService::class)->loadFromDatabase();
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
            'data' => $order->load(['customer', 'branch', 'rider', 'orderItems.product'])
        ]);
    }

    /**
     * Get all customers
     */
    public function getCustomers(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    /**
     * Get customer by ID
     */
    public function getCustomer(string $id): JsonResponse
    {
        $customer = Customer::with('orders')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    /**
     * Update customer
     */
    public function updateCustomer(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:customers,email,' . $id,
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'postal_code' => 'sometimes|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $customer = Customer::findOrFail($id);
        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    /**
     * Delete customer
     */
    public function deleteCustomer(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        
        // Check if customer has paid orders that are not yet delivered
        $paidUndeliveredOrders = $customer->orders()
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->count();
            
        if ($paidUndeliveredOrders > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete customer. Customer has {$paidUndeliveredOrders} paid order(s) that have not been delivered yet. Please complete or cancel these orders first."
            ], 422);
        }
        
        // Soft delete the customer (mark as inactive instead of hard delete)
        $customer->update(['is_active' => false]);
        
        return response()->json([
            'success' => true,
            'message' => 'Customer deactivated successfully. Customer data preserved due to order history.'
        ]);
    }

    /**
     * Get customers with pending orders
     */
    public function getCustomersWithPendingOrders(): JsonResponse
    {
        $customers = Customer::whereHas('orders', function ($query) {
            $query->where('payment_status', 'paid')
                  ->whereNotIn('status', ['delivered', 'cancelled']);
        })
        ->with(['orders' => function ($query) {
            $query->where('payment_status', 'paid')
                  ->whereNotIn('status', ['delivered', 'cancelled'])
                  ->select('id', 'customer_id', 'status', 'payment_status', 'total_amount', 'created_at');
        }])
        ->get();

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(): JsonResponse
    {
        $stats = [
            'total_branches' => Branch::count(),
            'total_customers' => Customer::count(),
            'total_riders' => Rider::count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'completed_orders' => Order::where('status', 'delivered')->count(),
            'total_revenue' => Order::where('status', 'delivered')->sum('total_amount'),
            'active_riders' => Rider::where('status', 'available')->count(),
            'low_stock_products' => Product::where('stock_quantity', '<', 10)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(Request $request): JsonResponse
    {
        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays($period);

        $revenue = Order::where('status', 'delivered')
            ->where('created_at', '>=', $startDate)
            ->sum('total_amount');

        $ordersCount = Order::where('created_at', '>=', $startDate)->count();

        $dailyRevenue = Order::where('status', 'delivered')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $revenue,
                'orders_count' => $ordersCount,
                'average_order_value' => $ordersCount > 0 ? $revenue / $ordersCount : 0,
                'daily_revenue' => $dailyRevenue
            ]
        ]);
    }

    /**
     * Get accounting report (super admin only)
     * Shows cost price, sales price, profit, delivery fees, and tax
     */
    public function getAccountingReport(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();
        
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super admin access required.'
            ], 403);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'branch_id' => 'nullable|uuid|exists:branches,id',
        ]);

        $startDate = $request->get('start_date') ? \Carbon\Carbon::parse($request->get('start_date'))->startOfDay() : null;
        $endDate = $request->get('end_date') ? \Carbon\Carbon::parse($request->get('end_date'))->endOfDay() : null;
        $status = $request->get('status');
        $branchId = $request->get('branch_id');

        $ordersQuery = Order::with(['orderItems.product']);

        if ($startDate) {
            $ordersQuery->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $ordersQuery->where('created_at', '<=', $endDate);
        }
        if ($status) {
            $ordersQuery->where('status', $status);
        }
        if ($branchId) {
            $ordersQuery->where('branch_id', $branchId);
        }

        $orders = $ordersQuery->get();

        $totalCostPrice = 0;
        $totalSalesPrice = 0;
        $totalDeliveryFee = 0;
        $totalTax = 0;
        $totalDiscount = 0;
        $itemsSold = 0;

        $productBreakdown = [];

        foreach ($orders as $order) {
            $totalDeliveryFee += (float) $order->delivery_fee;
            $totalTax += (float) $order->tax_amount;

            foreach ($order->orderItems as $item) {
                $product = $item->product;
                $quantity = (int) $item->quantity;
                $itemsSold += $quantity;

                $costPrice = $product ? (float) $product->cost_price * $quantity : 0;
                $salesPrice = (float) $item->total_price;
                $discount = (float) $item->discount_amount;

                $totalCostPrice += $costPrice;
                $totalSalesPrice += $salesPrice;
                $totalDiscount += $discount;

                if ($product) {
                    $productId = $product->id;
                    if (!isset($productBreakdown[$productId])) {
                        $productBreakdown[$productId] = [
                            'product_id' => $productId,
                            'product_name' => $product->name,
                            'sku' => $product->sku,
                            'quantity_sold' => 0,
                            'cost_price_total' => 0,
                            'sales_price_total' => 0,
                            'discount_total' => 0,
                            'profit' => 0,
                        ];
                    }
                    $productBreakdown[$productId]['quantity_sold'] += $quantity;
                    $productBreakdown[$productId]['cost_price_total'] += $costPrice;
                    $productBreakdown[$productId]['sales_price_total'] += $salesPrice;
                    $productBreakdown[$productId]['discount_total'] += $discount;
                    $productBreakdown[$productId]['profit'] = 
                        $productBreakdown[$productId]['sales_price_total'] - $productBreakdown[$productId]['cost_price_total'];
                }
            }
        }

        $grossProfit = $totalSalesPrice - $totalCostPrice;
        $netProfit = $grossProfit + $totalDeliveryFee + $totalTax;

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_orders' => $orders->count(),
                    'items_sold' => $itemsSold,
                    'total_cost_price' => round($totalCostPrice, 2),
                    'total_sales_price' => round($totalSalesPrice, 2),
                    'total_discount' => round($totalDiscount, 2),
                    'gross_profit' => round($grossProfit, 2),
                    'total_delivery_fee' => round($totalDeliveryFee, 2),
                    'total_tax' => round($totalTax, 2),
                    'net_profit' => round($netProfit, 2),
                    'profit_margin' => $totalSalesPrice > 0 ? round(($grossProfit / $totalSalesPrice) * 100, 2) : 0,
                ],
                'filters' => [
                    'start_date' => $startDate ? $startDate->toDateString() : null,
                    'end_date' => $endDate ? $endDate->toDateString() : null,
                    'status' => $status,
                    'branch_id' => $branchId,
                ],
                'product_breakdown' => array_values($productBreakdown),
            ]
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            '*.key' => 'required|string',
            '*.value' => 'nullable|string',
        ]);

        $results = [];

        foreach ($validated as $item) {
            $setting = Setting::updateOrCreate(
                ['key' => $item['key']],
                ['value' => $item['value']]
            );
            $results[] = $setting;
        }

        cache()->forget('app_settings');
        
        // If mail settings were updated, clear config cache and reload mail config
        $mailKeys = ['mail_mailer', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name'];
        $mailSettingsUpdated = false;
        foreach ($validated as $item) {
            if (in_array($item['key'], $mailKeys)) {
                $mailSettingsUpdated = true;
                break;
            }
        }
        
        if ($mailSettingsUpdated) {
            // Clear config cache
            cache()->forget('config');
            
            // Reload mail configuration from database
            app(MailConfigService::class)->loadFromDatabase();
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully' . ($mailSettingsUpdated ? ' (Mail config reloaded)' : ''),
            'data' => $results,
        ]);
    }

    public function getSettings(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,gif,svg,webp|max:2048',
        ]);

        $file = $request->file('logo');
        $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('logos', $filename, 'public');

        $fullUrl = asset('storage/' . $path);

        Setting::updateOrCreate(
            ['key' => 'logo'],
            ['value' => $fullUrl]
        );

        cache()->forget('app_settings');

        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'data' => [
                'logo' => $fullUrl,
            ],
        ]);
    }

    public function createSlider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_path' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'link' => 'nullable|string',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        // Get the uploaded file
        $file = $request->file('image_path');

        // Generate a UUID-based filename with the original extension
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Store the file in the "public/sliders" directory
        $path = $file->storeAs('sliders', $filename, 'public');

        // Save to database
        $slider = Slider::create([
            'image_path' => $path, // e.g. "sliders/8b9c9b21-6a9a-4c4e-8e55-7ad8129e6bdf.jpg"
            'link' => $validated['link'] ?? null,
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Slider created successfully',
            'data' => $slider,
        ], 201);
    }

    public function getSliders(): JsonResponse
    {
        $sliders = Slider::all();

        return response()->json([
            'success' => true,
            'data' => $sliders,
        ]);
    }

    /**
     * Update slider (with optional image)
     */
    public function updateSlider(Request $request, string $id): JsonResponse
    {
        try {
            $slider = Slider::findOrFail($id);

            $validated = $request->validate([
                'image_path' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
                'link' => 'nullable|string',
                'title' => 'nullable|string',
                'description' => 'nullable|string',
            ]);

            // Handle image upload if provided
            if ($request->hasFile('image_path')) {
                // Delete old image if it exists
                if (!empty($slider->image_path)) {
                    Storage::disk('public')->delete($slider->image_path);
                }

                // Get the new uploaded file
                $file = $request->file('image_path');
                
                // Generate a UUID-based filename with the original extension
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

                // Store the file in the "public/sliders" directory
                $path = $file->storeAs('sliders', $filename, 'public');
                
                $validated['image_path'] = $path;
            }

            // Update slider with validated data
            $slider->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Slider updated successfully',
                'data' => $slider->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update slider', [
                'slider_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update slider'
            ], 500);
        }
    }

    /**
     * Delete slider
     */
    public function deleteSlider(string $id): JsonResponse
    {
        try {
            $slider = Slider::findOrFail($id);

            // Delete associated image
            if (!empty($slider->image_path)) {
                Storage::disk('public')->delete($slider->image_path);
            }

            $slider->delete();

            return response()->json([
                'success' => true,
                'message' => 'Slider deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete slider'
            ], 500);
        }
    }

    // ===========================
    // ADS MANAGEMENT METHODS
    // ===========================

    /**
     * Get active ads (public)
     */
    public function getActiveAds(): JsonResponse
    {
        try {
            // Get ads with status='active' (no date filtering for public access)
            $ads = \App\Models\Ad::where('status', 'active')
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ads'
            ], 500);
        }
    }

    /**
     * Get ads by position (public)
     */
    public function getAdsByPosition(string $position): JsonResponse
    {
        try {
            // Get ads with status='active' by position (no date filtering for public access)
            $ads = \App\Models\Ad::where('status', 'active')
                ->where('position', $position)
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $ads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ads'
            ], 500);
        }
    }

    /** Valid ad position values (for route disambiguation). */
    private const AD_POSITIONS = ['banner', 'sidebar', 'popup', 'in_content'];

    /**
     * Public: GET /api/ads/{idOrPosition}
     * - If {idOrPosition} is a position (banner, sidebar, popup, in_content), return list of active ads for that position.
     * - Otherwise treat as ad ID (UUID) and return that single ad (or 404).
     */
    public function getAdByIdOrAdsByPosition(string $idOrPosition): JsonResponse
    {
        if (in_array($idOrPosition, self::AD_POSITIONS, true)) {
            $ads = \App\Models\Ad::where('status', 'active')
                ->where('position', $idOrPosition)
                ->with('products')
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
            $ads->transform(fn ($ad) => $this->formatAdForResponse($ad));
            return response()->json([
                'success' => true,
                'data' => $ads,
            ]);
        }

        try {
            $ad = \App\Models\Ad::with('products')->findOrFail($idOrPosition);
            return response()->json([
                'success' => true,
                'data' => $this->formatAdForResponse($ad),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ad not found',
            ], 404);
        }
    }

    /**
     * Get all ads (admin)
     */
    public function getAds(Request $request): JsonResponse
    {
        try {
            $query = \App\Models\Ad::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('position')) {
                $query->where('position', $request->position);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $ads = $query->with('products')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $ads->getCollection()->transform(fn ($ad) => $this->formatAdForResponse($ad));

            return response()->json([
                'success' => true,
                'data' => $ads,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ads'
            ], 500);
        }
    }

    /**
     * Normalize product_ids from request (array, comma-separated string, or JSON string).
     */
    private function normalizeProductIds(mixed $productIds): array
    {
        if (is_array($productIds)) {
            return array_values(array_filter($productIds, fn ($id) => is_string($id) && strlen($id) > 0));
        }
        if (is_string($productIds)) {
            $decoded = json_decode($productIds, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($id) => is_string($id) && strlen($id) > 0));
            }
            return array_values(array_filter(array_map('trim', explode(',', $productIds))));
        }
        return [];
    }

    /**
     * Format a single ad for API response (includes full URLs for media).
     */
    private function formatAdForResponse(\App\Models\Ad $ad): array
    {
        $data = $ad->toArray();
        $data['media_url_full'] = ! empty($data['media_url'])
            ? asset('storage/' . $data['media_url'])
            : null;
        $data['thumbnail_url_full'] = ! empty($data['thumbnail_url'])
            ? asset('storage/' . $data['thumbnail_url'])
            : null;
        $data['discount_type'] = $ad->discount_type;
        $data['discount_value'] = $ad->discount_value;
        $data['products'] = $ad->relationLoaded('products') ? $ad->products : $ad->products()->get();
        return $data;
    }

    /**
     * Create new ad
     */
    public function createAd(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:image,video',
            'media_file' => 'required|file|mimes:jpg,jpeg,png,gif,mp4,avi,mov,wmv|max:10240', // 10MB max
            'position' => 'required|in:banner,sidebar,popup,in_content',
            'status' => 'nullable|in:active,inactive,scheduled',
            'priority' => 'integer|min:0|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'discount_type' => 'nullable|string|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'ad_discount_type' => 'nullable|string|in:percent,fixed',
            'ad_discount_value' => 'nullable|numeric|min:0',
            'product_ids' => 'nullable', // array or comma-separated string or JSON string; normalized in code
        ]);

        try {
            $productIds = $this->normalizeProductIds($request->input('product_ids'));
            if (! empty($productIds)) {
                $exists = \App\Models\Product::whereIn('id', $productIds)->pluck('id')->toArray();
                $missing = array_diff($productIds, $exists);
                if (! empty($missing)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid product_ids: ' . implode(', ', $missing) . ' do not exist.',
                    ], 422);
                }
            }

            // Handle file upload
            $file = $request->file('media_file');
            $type = $request->type;
            
            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = 'ad_' . time() . '_' . uniqid() . '.' . $extension;
            
            // Store file in appropriate directory
            $directory = $type === 'video' ? 'ads/videos' : 'ads/images';
            $filePath = $file->storeAs($directory, $filename, 'public');
            
            // Create ad record: accept discount_type/discount_value or ad_discount_type/ad_discount_value
            $adData = $request->except(['media_file', 'ad_discount_type', 'ad_discount_value', 'product_ids']);
            $adData['media_url'] = $filePath;
            $adData['status'] = $request->input('status', 'active');
            $adData['discount_type'] = $request->input('discount_type') ?? $request->input('ad_discount_type');
            $adData['discount_value'] = $request->input('discount_value') ?? $request->input('ad_discount_value');
            
            // Generate thumbnail for videos
            if ($type === 'video') {
                $adData['thumbnail_url'] = $this->generateVideoThumbnail($filePath);
            }
            
            $ad = \App\Models\Ad::create($adData);
            $ad->products()->sync($productIds);

            return response()->json([
                'success' => true,
                'message' => 'Ad created successfully',
                'data' => $this->formatAdForResponse($ad->fresh()->load('products')),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific ad (admin, requires auth).
     */
    public function getAd(string $id): JsonResponse
    {
        try {
            $ad = \App\Models\Ad::with('products')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $this->formatAdForResponse($ad),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ad not found'
            ], 404);
        }
    }

    /**
     * Get a single ad by ID (public, no auth). Same response shape as getAd.
     */
    public function getAdPublic(string $id): JsonResponse
    {
        try {
            $ad = \App\Models\Ad::with('products')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $this->formatAdForResponse($ad),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ad not found'
            ], 404);
        }
    }

    /**
     * Update ad
     */
    public function updateAd(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:image,video',
            'media_file' => 'sometimes|file|mimes:jpg,jpeg,png,gif,mp4,avi,mov,wmv|max:10240',
            'position' => 'sometimes|in:banner,sidebar,popup,in_content',
            'status' => 'sometimes|in:active,inactive,scheduled',
            'priority' => 'integer|min:0|max:100',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'discount_type' => 'sometimes|nullable|string|in:percent,fixed',
            'discount_value' => 'sometimes|nullable|numeric|min:0',
            'ad_discount_type' => 'sometimes|nullable|string|in:percent,fixed',
            'ad_discount_value' => 'sometimes|nullable|numeric|min:0',
            'product_ids' => 'sometimes|nullable',
        ]);

        try {
            $ad = \App\Models\Ad::findOrFail($id);
            $productIdsForUpdate = $this->normalizeProductIds($request->input('product_ids'));
            if ($request->has('product_ids') && ! empty($productIdsForUpdate)) {
                $exists = \App\Models\Product::whereIn('id', $productIdsForUpdate)->pluck('id')->toArray();
                $missing = array_diff($productIdsForUpdate, $exists);
                if (! empty($missing)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid product_ids: ' . implode(', ', $missing) . ' do not exist.',
                    ], 422);
                }
            }

            $updateData = $request->except(['media_file', 'ad_discount_type', 'ad_discount_value', 'product_ids']);
            $updateData['discount_type'] = $request->input('discount_type') ?? $request->input('ad_discount_type') ?? $ad->discount_type;
            $updateData['discount_value'] = $request->input('discount_value') ?? $request->input('ad_discount_value') ?? $ad->discount_value;

            // Handle new file upload if provided
            if ($request->hasFile('media_file')) {
                // Delete old file
                if ($ad->media_url && Storage::disk('public')->exists($ad->media_url)) {
                    Storage::disk('public')->delete($ad->media_url);
                }
                if ($ad->thumbnail_url && Storage::disk('public')->exists($ad->thumbnail_url)) {
                    Storage::disk('public')->delete($ad->thumbnail_url);
                }

                // Upload new file
                $file = $request->file('media_file');
                $type = $request->type ?? $ad->type;
                
                $extension = $file->getClientOriginalExtension();
                $filename = 'ad_' . time() . '_' . uniqid() . '.' . $extension;
                
                $directory = $type === 'video' ? 'ads/videos' : 'ads/images';
                $filePath = $file->storeAs($directory, $filename, 'public');
                
                $updateData['media_url'] = $filePath;
                
                // Generate thumbnail for videos
                if ($type === 'video') {
                    $updateData['thumbnail_url'] = $this->generateVideoThumbnail($filePath);
                }
            }

            $ad->update($updateData);
            if ($request->has('product_ids')) {
                $ad->products()->sync($productIdsForUpdate);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ad updated successfully',
                'data' => $this->formatAdForResponse($ad->fresh()->load('products')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete ad
     */
    public function deleteAd(string $id): JsonResponse
    {
        try {
            $ad = \App\Models\Ad::findOrFail($id);
            
            // Delete associated files
            if ($ad->media_url && Storage::disk('public')->exists($ad->media_url)) {
                Storage::disk('public')->delete($ad->media_url);
            }
            if ($ad->thumbnail_url && Storage::disk('public')->exists($ad->thumbnail_url)) {
                Storage::disk('public')->delete($ad->thumbnail_url);
            }
            
            $ad->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ad deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track ad click
     */
    public function trackAdClick(string $id): JsonResponse
    {
        try {
            $ad = \App\Models\Ad::findOrFail($id);
            $ad->incrementClicks();

            return response()->json([
                'success' => true,
                'message' => 'Click tracked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track click'
            ], 500);
        }
    }

    // ===========================
    // TRANSACTION MANAGEMENT METHODS
    // ===========================

    /**
     * Get all transactions
     */
    public function getTransactions(Request $request): JsonResponse
    {
        try {
            $query = \App\Models\TransactionDetail::with(['order', 'invoice']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $transactions = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions'
            ], 500);
        }
    }

    /**
     * Get specific transaction
     */
    public function getTransaction(string $id): JsonResponse
    {
        try {
            $transaction = \App\Models\TransactionDetail::with(['order', 'invoice'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    /**
     * Get transactions for specific order
     */
    public function getOrderTransactions(string $orderId): JsonResponse
    {
        try {
            $transactions = \App\Models\TransactionDetail::with(['order', 'invoice'])
                ->where('order_id', $orderId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order transactions'
            ], 500);
        }
    }

    /**
     * Generate thumbnail for video ads
     */
    private function generateVideoThumbnail(string $videoPath): ?string
    {
        try {
            // For now, return null - you can implement FFmpeg thumbnail generation later
            // This would require FFmpeg to be installed on the server
            return null;
            
            // Example implementation with FFmpeg (commented out):
            /*
            $thumbnailPath = 'ads/thumbnails/' . pathinfo($videoPath, PATHINFO_FILENAME) . '_thumb.jpg';
            $fullVideoPath = storage_path('app/public/' . $videoPath);
            $fullThumbnailPath = storage_path('app/public/' . $thumbnailPath);
            
            // Create thumbnail directory if it doesn't exist
            Storage::disk('public')->makeDirectory('ads/thumbnails');
            
            // Generate thumbnail using FFmpeg
            $command = "ffmpeg -i {$fullVideoPath} -ss 00:00:01 -vframes 1 {$fullThumbnailPath}";
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($fullThumbnailPath)) {
                return $thumbnailPath;
            }
            */
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update admin profile picture
     */
    public function updateProfilePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            // Delete old profile picture if it exists
            if (!empty($user->profile_picture)) {
                $oldPath = str_replace('/storage/', '', $user->profile_picture);
                Storage::disk('public')->delete($oldPath);
            }

            // Handle new profile picture upload
            $file = $request->file('profile_picture');
            $extension = $file->getClientOriginalExtension();
            $filename = $user->id . '.' . $extension;
            $path = $file->storeAs('profiles/admins', $filename, 'public');
            
            // Update user profile picture
            $user->update([
                'profile_picture' => Storage::url($path)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'data' => [
                    'profile_picture' => $user->profile_picture
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Admin profile picture update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile picture. Please try again.',
            ], 500);
        }
    }
}
