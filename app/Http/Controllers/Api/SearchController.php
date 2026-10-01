<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across all entities
     */
    public function globalSearch(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'type' => 'nullable|in:products,orders,customers,branches,all',
            'limit' => 'nullable|integer|min:1|max:50',
        ], [
            'query.required' => 'Search query is required.',
            'query.min' => 'Search query must be at least 2 characters.',
            'query.max' => 'Search query cannot exceed 100 characters.',
            'type.in' => 'Invalid search type.',
            'limit.max' => 'Limit cannot exceed 50 results.',
        ]);

        $searchQuery = $request->input('query');
        $type = $request->get('type', 'all');
        $limit = $request->get('limit', 10);

        $results = [];

        if ($type === 'all' || $type === 'products') {
            $results['products'] = $this->searchProducts($searchQuery, $limit);
            $this->attachVariantDisplayPrices($results['products']);
        }

        if ($type === 'all' || $type === 'orders') {
            $results['orders'] = $this->searchOrders($searchQuery, $limit);
        }

        if ($type === 'all' || $type === 'customers') {
            $results['customers'] = $this->searchCustomers($searchQuery, $limit);
        }

        if ($type === 'all' || $type === 'branches') {
            $results['branches'] = $this->searchBranches($searchQuery, $limit);
        }

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'query' => $searchQuery,
                'type' => $type,
                'total_results' => array_sum(array_map('count', $results))
            ]
        ]);
    }

    /**
     * Advanced product search with filters
     */
    public function advancedProductSearch(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'nullable|string|max:100',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'brand' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'size' => 'nullable|string|max:50',
            'dimensions' => 'nullable|string|max:100',
            'is_featured' => 'nullable|boolean',
            'is_digital' => 'nullable|boolean',
            'in_stock' => 'nullable|boolean',
            'sort_by' => 'nullable|in:name,price,created_at,stock_quantity',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Product::with(['category', 'branch', 'ads', 'variants']);

        // Text search
        if ($request->filled('query')) {
            $searchTerm = $request->input('query');
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('short_description', 'like', "%{$searchTerm}%")
                  ->orWhere('brand', 'like', "%{$searchTerm}%")
                  ->orWhere('model', 'like', "%{$searchTerm}%")
                  ->orWhere('sku', 'like', "%{$searchTerm}%");
            });
        }

        // Filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->has('brand')) {
            $query->where('brand', 'like', "%{$request->brand}%");
        }

        if ($request->has('color')) {
            $query->where(function ($products) use ($request) {
                $products->where('color', 'like', "%{$request->color}%")
                    ->orWhereHas('variants', fn ($variants) => $variants->where('color', 'like', "%{$request->color}%"));
            });
        }

        if ($request->has('size')) {
            $query->where(function ($products) use ($request) {
                $products->where('size', 'like', "%{$request->size}%")
                    ->orWhereHas('variants', fn ($variants) => $variants->where('size', 'like', "%{$request->size}%"));
            });
        }

        if ($request->has('dimensions')) {
            $query->where(function ($products) use ($request) {
                $products->where('dimensions', 'like', "%{$request->dimensions}%")
                    ->orWhereHas('variants', fn ($variants) => $variants->where('dimensions', 'like', "%{$request->dimensions}%"));
            });
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->is_featured);
        }

        if ($request->has('is_digital')) {
            $query->where('is_digital', $request->is_digital);
        }

        if ($request->has('in_stock') && $request->in_stock) {
            $query->where('stock_quantity', '>', 0);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage)->appends($request->except('page'));
        $this->attachVariantDisplayPrices($products->getCollection());

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Related products for a given product
     */
    public function relatedProducts(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'in_stock' => 'nullable|boolean',
        ]);

        $product = Product::findOrFail($id);

        $perPage = (int) $request->get('per_page', 12);
        $inStockOnly = filter_var($request->get('in_stock', false), FILTER_VALIDATE_BOOLEAN);

        $query = Product::with(['category', 'branch', 'ads'])
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->where(function ($q) use ($product) {
                // Same category
                $q->where('category_id', $product->category_id)
                    // Or same brand
                    ->orWhere(function ($qb) use ($product) {
                        if (!empty($product->brand)) {
                            $qb->where('brand', $product->brand);
                        }
                    })
                    // Or overlapping tags (if tags array is present)
                    ->orWhere(function ($qb) use ($product) {
                        if (is_array($product->tags) && count($product->tags) > 0) {
                            foreach ($product->tags as $tag) {
                                $qb->orWhereJsonContains('tags', $tag);
                            }
                        }
                    });
            });

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($inStockOnly) {
            $query->where('stock_quantity', '>', 0);
        }

        // Prioritize featured, then recent
        $results = $query
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->except('page'));
        $this->attachVariantDisplayPrices($results->getCollection());

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Search products
     */
    private function searchProducts(string $query, int $limit)
    {
        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%")
            ->orWhere('brand', 'like', "%{$query}%")
            ->with(['category', 'branch', 'ads'])
            ->limit($limit)
            ->get();
        $this->attachVariantDisplayPrices($products);
        return $products;
    }

    /**
     * Search orders
     */
    private function searchOrders(string $query, int $limit)
    {
        return Order::where('order_number', 'like', "%{$query}%")
            ->orWhere('delivery_address', 'like', "%{$query}%")
            ->orWhere('delivery_phone', 'like', "%{$query}%")
            ->with(['customer', 'branch', 'rider'])
            ->limit($limit)
            ->get();
    }

    /**
     * Search customers
     */
    private function searchCustomers(string $query, int $limit)
    {
        return Customer::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->limit($limit)
            ->get();
    }

    /**
     * Search branches
     */
    private function searchBranches(string $query, int $limit)
    {
        return Branch::where('name', 'like', "%{$query}%")
            ->orWhere('address', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->limit($limit)
            ->get();
    }

    private function attachVariantDisplayPrices($products): void
    {
        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $variant->setAttribute(
                    'display_price',
                    $product->getUnitPriceWithAdDiscount((float) $variant->price)
                );
            }
        }
    }

    /**
     * Get search suggestions
     */
    public function getSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:50',
            'type' => 'nullable|in:products,customers,branches',
        ], [
            'query.required' => 'Search query is required.',
            'query.min' => 'Search query must be at least 2 characters.',
        ]);

        $query = $request->input('query');
        $type = $request->get('type', 'products');

        $suggestions = [];

        switch ($type) {
            case 'products':
                $suggestions = Product::where('name', 'like', "%{$query}%")
                    ->orWhere('brand', 'like', "%{$query}%")
                    ->select('name', 'brand')
                    ->distinct()
                    ->limit(10)
                    ->get()
                    ->pluck('name')
                    ->toArray();
                break;
            case 'customers':
                $suggestions = Customer::where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->select('name', 'email')
                    ->limit(10)
                    ->get()
                    ->map(function($customer) {
                        return $customer->name . ' (' . $customer->email . ')';
                    })
                    ->toArray();
                break;
            case 'branches':
                $suggestions = Branch::where('name', 'like', "%{$query}%")
                    ->select('name')
                    ->limit(10)
                    ->get()
                    ->pluck('name')
                    ->toArray();
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $suggestions
        ]);
    }
}
