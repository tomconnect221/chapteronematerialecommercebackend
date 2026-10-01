<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    /**
     * Check admin privileges
     */
    private function checkAdminPrivileges(): bool
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'customer';
        return in_array($userRole, ['admin', 'subadmin']);
    }

    /**
     * Get all invoices (Admin/Subadmin only)
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkAdminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            $query = Invoice::with(['order', 'customer']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            // Search by invoice number
            if ($request->has('search')) {
                $query->where('invoice_number', 'like', '%' . $request->search . '%');
            }

            $invoices = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch invoices', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoices'
            ], 500);
        }
    }

    /**
     * Get specific invoice (Admin/Subadmin only)
     */
    public function show(string $id): JsonResponse
    {
        if (!$this->checkAdminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            // Try to find by ID first, then by invoice_number
            $invoice = Invoice::with(['order', 'customer', 'order.orderItems.product'])
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                          ->orWhere('invoice_number', $id);
                })
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch invoice', [
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }
    }

    /**
     * Get invoices for a specific order (Admin/Subadmin only)
     */
    public function getByOrder(string $orderId): JsonResponse
    {
        if (!$this->checkAdminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            $invoices = Invoice::with(['customer'])
                ->where('order_id', $orderId)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch invoices for order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoices for order'
            ], 500);
        }
    }

    /**
     * Get customer's invoices (Customer only)
     */
    public function getCustomerInvoices(Request $request): JsonResponse
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'customer';

        if ($userRole !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Customer privileges required.'
            ], 403);
        }

        try {
            // Get invoices through orders relationship
            $query = Invoice::with(['order'])
                ->whereHas('order', function ($q) use ($user) {
                    $q->where('customer_id', $user->id);
                });

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $invoices = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $invoices
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch customer invoices', [
                'customer_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your invoices'
            ], 500);
        }
    }

    /**
     * Get specific customer invoice (Customer only)
     */
    public function getCustomerInvoice(string $id): JsonResponse
    {
        $user = Auth::user();
        $userRole = $user->role ?? 'customer';

        if ($userRole !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Customer privileges required.'
            ], 403);
        }

        try {
            // Try to find by ID first, then by invoice_number
            $invoice = Invoice::with(['order', 'order.orderItems.product'])
                ->whereHas('order', function ($q) use ($user) {
                    $q->where('customer_id', $user->id);
                })
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                          ->orWhere('invoice_number', $id);
                })
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch customer invoice', [
                'customer_id' => $user->id,
                'invoice_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }
    }

    /**
     * Update invoice status (Admin/Subadmin only)
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        if (!$this->checkAdminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,paid,failed,refunded'
        ]);

        try {
            $invoice = Invoice::findOrFail($id);
            
            $invoice->update([
                'status' => $request->status,
                'paid_at' => $request->status === 'paid' ? now() : null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice status updated successfully',
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update invoice status', [
                'invoice_id' => $id,
                'status' => $request->status,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice status'
            ], 500);
        }
    }

    /**
     * Get invoice statistics (Admin/Subadmin only)
     */
    public function getStatistics(): JsonResponse
    {
        if (!$this->checkAdminPrivileges()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin privileges required.'
            ], 403);
        }

        try {
            $stats = [
                'total_invoices' => Invoice::count(),
                'paid_invoices' => Invoice::where('status', 'paid')->count(),
                'pending_invoices' => Invoice::where('status', 'pending')->count(),
                'failed_invoices' => Invoice::where('status', 'failed')->count(),
                'refunded_invoices' => Invoice::where('status', 'refunded')->count(),
                'total_revenue' => Invoice::where('status', 'paid')->sum('amount'),
                'pending_revenue' => Invoice::where('status', 'pending')->sum('amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch invoice statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch invoice statistics'
            ], 500);
        }
    }
}
