<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    public function __construct()
    {
        //
    }

    /**
     * Check if user has subadmin privileges
     */
    private function checkSubadminPrivileges(): bool
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'customer';
        return $userRole === 'subadmin';
    }

    /**
     * Display a listing of inventory items.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkSubadminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Subadmin privileges required.'
            ], 403);
        }

        try {
            $query = Inventory::with('product')
                ->where('is_active', true);

            // Filter by low stock
            if ($request->has('low_stock') && $request->boolean('low_stock')) {
                $query->whereRaw('quantity_in_stock - quantity_reserved <= reorder_level');
            }

            // Filter by out of stock
            if ($request->has('out_of_stock') && $request->boolean('out_of_stock')) {
                $query->whereRaw('quantity_in_stock - quantity_reserved <= 0');
            }

            // Search by product name
            if ($request->has('search')) {
                $query->whereHas('product', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%');
                });
            }

            $inventory = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $inventory
            ]);
        } catch (\Exception $e) {
            Log::error('Inventory index failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory'
            ], 500);
        }
    }

    // Note: Store method removed - inventory is now automatically created when products are created

    /**
     * Display the specified inventory item.
     */
    public function show(string $id): JsonResponse
    {
        if (!$this->checkSubadminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Subadmin privileges required.'
            ], 403);
        }

        try {
            $inventory = Inventory::with('product')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $inventory
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory not found'
            ], 404);
        }
    }

    /**
     * Update the specified inventory item.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if (!$this->checkSubadminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Subadmin privileges required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'quantity_in_stock' => 'sometimes|integer|min:0',
            'quantity_reserved' => 'sometimes|integer|min:0',
            'reorder_level' => 'sometimes|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $inventory = Inventory::findOrFail($id);
            if ($inventory->product?->variants()->exists() && ($request->has('quantity_in_stock') || $request->has('quantity_reserved'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'This product tracks stock by option. Restock a specific option, or change option stock from product management.',
                ], 422);
            }
            $inventory->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Inventory updated successfully',
                'data' => $inventory->load('product')
            ]);
        } catch (\Exception $e) {
            Log::error('Inventory update failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update inventory'
            ], 500);
        }
    }

    /**
     * Remove the specified inventory item.
     */
    public function destroy(string $id): JsonResponse
    {
        if (!$this->checkSubadminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Subadmin privileges required.'
            ], 403);
        }

        try {
            $inventory = Inventory::findOrFail($id);
            $inventory->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Inventory deactivated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Inventory deletion failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete inventory'
            ], 500);
        }
    }

    /**
     * Restock inventory.
     */
    public function restock(Request $request, string $id): JsonResponse
    {
        if (!$this->checkSubadminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Subadmin privileges required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
            'supplier' => 'nullable|string|max:255',
            'variant_id' => 'nullable|uuid|exists:product_variants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $inventory = Inventory::findOrFail($id);
            $product = $inventory->product;
            $variants = $product?->variants()->get() ?? collect();

            if ($variants->isNotEmpty()) {
                $variant = $variants->firstWhere('id', $request->input('variant_id'));
                if (!$variant) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Choose a valid product option to restock.',
                        'errors' => ['variant_id' => ['The selected option does not belong to this product.']],
                    ], 422);
                }

                $variant->increment('stock_quantity', (int) $request->quantity);
                $product->stock_quantity = $variants->sum('stock_quantity');
                $product->save();

                if ($request->filled('supplier')) {
                    $inventory->refresh();
                    $inventory->supplier = $request->supplier;
                    $inventory->last_restocked = now();
                    $inventory->save();
                }
            } else {
                $inventory->addStock($request->quantity, $request->supplier);
            }

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => $inventory->fresh()->load('product')
            ]);
        } catch (\Exception $e) {
            Log::error('Inventory restock failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to restock inventory'
            ], 500);
        }
    }

    /**
     * Get low stock alerts.
     */
    public function lowStockAlerts(): JsonResponse
    {
        if (!$this->checkSubadminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Subadmin privileges required.'
            ], 403);
        }

        try {
            $lowStockItems = Inventory::with('product')
                ->where('is_active', true)
                ->whereRaw('quantity_in_stock - quantity_reserved <= reorder_level')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $lowStockItems
            ]);
        } catch (\Exception $e) {
            Log::error('Low stock alerts failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch low stock alerts'
            ], 500);
        }
    }
}