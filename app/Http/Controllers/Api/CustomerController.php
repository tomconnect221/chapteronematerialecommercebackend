<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateProfilePictureRequest;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Events\OrderCreated;
use App\Mail\NewOrderForAdminEmail;
use App\Mail\OrderConfirmationEmail;
use App\Models\Subadmin;
use App\Models\User;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Get all branches
     */
    public function getBranches(): JsonResponse
    {
        $branches = Branch::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $branches
        ]);
    }

    /**
     * Get all categories
     */
    public function getCategories(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->with(['products' => fn ($q) => $q->where('is_active', true)->with('ads')])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get all products
     */
    public function getProducts(Request $request): JsonResponse
    {
        $query = Product::where('is_active', true)->with(['category', 'branch', 'ads', 'variants']);

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->get();
        $products->each(fn (Product $product) => $this->attachVariantDisplayPrices($product));

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
        $product = Product::where('is_active', true)
            ->with(['category', 'branch', 'ads', 'variants'])
            ->findOrFail($id);
        $this->attachVariantDisplayPrices($product);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function updateShippingAddresses(Request $request): JsonResponse
    {
        /** @var \App\Models\Customer $customer */
        $customer = Auth::guard('customer-api')->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $data = $request->validate([
            'shipping_addresses' => 'required|array',
            'shipping_addresses.*.address' => 'nullable|string|max:255',
            'shipping_addresses.*.city' => 'nullable|string|max:100',
            'shipping_addresses.*.state' => 'nullable|string|max:100',
            'shipping_addresses.*.postal_code' => 'nullable|string|max:20',
            'shipping_addresses.*.longitude' => 'nullable|numeric|between:-180,180',
            'shipping_addresses.*.latitude' => 'nullable|numeric|between:-90,90',
            'shipping_addresses.*.is_default' => 'sometimes|boolean',
        ]);

        // Ensure only one default address
        $addresses = collect($data['shipping_addresses'])->map(function ($addr) {
            $addr['is_default'] = !empty($addr['is_default']) && $addr['is_default'] == true;
            return $addr;
        });

        if (!$addresses->contains('is_default', true)) {
            // Set the first address as default if none specified
            $addresses[0]['is_default'] = true;
        } else {
            // If multiple marked default, keep only the first one as default
            $firstDefaultIndex = $addresses->search(fn($a) => $a['is_default'] === true);
            $addresses = $addresses->map(function ($a, $i) use ($firstDefaultIndex) {
                $a['is_default'] = $i === $firstDefaultIndex;
                return $a;
            });
        }

        // Save the updated list as JSON (make sure shipping_addresses is a casted attribute in the model)
        $customer->shipping_addresses = $addresses->values()->toArray();
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Shipping addresses updated successfully',
            'data' => $customer->shipping_addresses,
        ]);
    }

    /**
     * Create a new order
     */
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customer = Auth::guard('customer-api')->user();

        // 🔹 Fetch delivery fee from settings. Tax is per-product only (product.tax_rate); VAT from settings is not used.
        $settings = cache()->rememberForever('app_settings', function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        // Use delivery_fee from request if provided, otherwise use settings default
        $deliveryFee = isset($data['delivery_fee'])
            ? floatval($data['delivery_fee'])
            : (isset($settings['delivery_fee']) ? floatval($settings['delivery_fee']) : 0);

        // 🔹 Calculate order totals (tax per product using product.tax_rate)
        $subtotal = 0;
        $taxAmount = 0;
        $orderItems = [];

        foreach ($data['items'] as $itemIndex => $item) {
            $product = Product::findOrFail($item['product_id']);
            $variant = $this->resolveOrderVariant($product, $item, $itemIndex);
            $availableStock = $variant?->stock_quantity ?? $product->stock_quantity;

            if ($availableStock < $item['quantity']) {
                // Send notification to admins about insufficient stock
                $this->notificationService->sendToAdmins(
                    type: 'insufficient_stock',
                    title: 'Insufficient Stock Alert',
                    message: "Customer attempted to order {$item['quantity']} units of {$product->name}, but only {$availableStock} units available",
                    data: [],
                    options: [
                        'category' => 'inventory',
                        'priority' => 'high',
                        'action_url' => "/admin/products/{$product->id}",
                        'action_text' => 'View Product',
                        'metadata' => [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'requested_quantity' => $item['quantity'],
                            'available_quantity' => $availableStock,
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->name,
                        ]
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for product: {$product->name}. Only {$availableStock} units available."
                ], 400);
            }

            // Apply ad discount (from product's own ad fields or from linked active Ad)
            $originalPrice = (float) ($variant?->price ?? $product->price);
            $unitPrice = $product->getUnitPriceWithAdDiscount($variant ? (float) $variant->price : null);
            $discountPerUnit = $originalPrice - $unitPrice;
            $totalPrice = $unitPrice * $item['quantity'];
            $discountAmount = $discountPerUnit * $item['quantity'];
            $subtotal += $totalPrice;

            // Tax for this line using this product's tax_rate only (no global VAT).
            $productTaxRate = isset($product->tax_rate) ? (float) $product->tax_rate / 100 : 0;
            $lineTax = round($totalPrice * $productTaxRate, 2);
            $taxAmount += $lineTax;

            $orderItems[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'variant_options' => $this->variantSnapshot($variant),
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'discount_amount' => $discountAmount,
                'tax_amount' => $lineTax,
            ];
        }

        // 🔹 Total tax (sum of per-product tax) & Delivery Fee (all included in amount sent to Paystack)
        $taxAmount = round($taxAmount, 2);
        $totalAmount = round($subtotal + $taxAmount + $deliveryFee, 2);

        // 🔹 Initialize payment with Paystack (amount = subtotal + tax + delivery_fee)
        $paystackService = new PaystackService();
        $paymentResponse = $paystackService->initializePayment(
            email: $customer->email,
            amount: $totalAmount,
            metadata: [
                'customer_id' => $customer->id,
                'branch_id' => $data['branch_id'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'delivery_address' => $data['delivery_address'],
                'delivery_city' => $data['delivery_city'] ?? null,
                'delivery_state' => $data['delivery_state'] ?? null,
                'delivery_longitude' => $data['delivery_longitude'] ?? null,
                'delivery_latitude' => $data['delivery_latitude'] ?? null,
                'delivery_phone' => $data['delivery_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'items' => $orderItems,
            ]
        );

        // Check if Paystack response is successful
        if (!$paymentResponse['status']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment: ' . ($paymentResponse['message'] ?? 'Unknown error')
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized successfully',
            'data' => [
                'authorization_url' => $paymentResponse['data']['authorization_url'],
                'reference' => $paymentResponse['data']['reference'],
                'access_code' => $paymentResponse['data']['access_code'],
            ]
        ], 200);
    }

    public function verifyPayment(Request $request, PaystackService $paystack)
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:255'],
        ]);
        $reference = $validated['reference'];

        try {
            $verification = $paystack->verifyPayment($reference);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Paystack payment verification request failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify payment at this time.',
            ], 502);
        }

        $paymentData = is_array($verification) ? ($verification['data'] ?? null) : null;
        if (
            !is_array($verification)
            || !($verification['status'] ?? false)
            || !is_array($paymentData)
            || ($paymentData['status'] ?? null) !== 'success'
        ) {
            Log::warning('Paystack payment verification was unsuccessful', [
                'reference' => $reference,
                'message' => is_array($verification) ? ($verification['message'] ?? null) : null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
            ], 400);
        }

        $metadata = $paymentData['metadata'] ?? null;
        $metadataValidator = Validator::make(is_array($metadata) ? $metadata : [], [
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'uuid'],
            'items.*.product_variant_id' => ['nullable', 'uuid'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['required', 'string'],
            'delivery_city' => ['nullable', 'string', 'max:100'],
            'delivery_state' => ['nullable', 'string', 'max:100'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($metadataValidator->fails()) {
            Log::error('Verified Paystack payment has invalid order metadata', [
                'reference' => $reference,
                'errors' => $metadataValidator->errors()->keys(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment was received, but its order details are incomplete. Please contact support.',
            ], 422);
        }

        $metadata = $metadataValidator->validated();
        $customer = Auth::guard('customer-api')->user();
        if (!$customer || (string) $metadata['customer_id'] !== (string) $customer->getAuthIdentifier()) {
            Log::warning('Customer attempted to verify a payment for another account', [
                'reference' => $reference,
                'customer_id' => $customer?->getAuthIdentifier(),
                'payment_customer_id' => $metadata['customer_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'This payment does not belong to the authenticated customer.',
            ], 403);
        }

        $result = DB::transaction(function () use ($metadata, $reference) {
            $existingOrder = Order::where('payment_reference', $reference)->lockForUpdate()->first();
            if ($existingOrder) {
                return ['order' => $existingOrder, 'created' => false];
            }

            $subtotal = 0;
            $orderItems = [];
            $products = [];

            foreach ($metadata['items'] as $itemIndex => $item) {
                $product = Product::whereKey($item['product_id'])->lockForUpdate()->firstOrFail();
                $variant = $this->resolveOrderVariant($product, $item, $itemIndex);
                $availableStock = $variant?->stock_quantity ?? $product->stock_quantity;
                if ($availableStock < $item['quantity']) {
                    return [
                        'insufficient_stock' => "Insufficient stock for the selected option of {$product->name}.",
                    ];
                }

                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : (float) $product->price;
                $totalPrice = isset($item['total_price']) ? (float) $item['total_price'] : $unitPrice * $item['quantity'];
                $discountAmount = isset($item['discount_amount']) ? (float) $item['discount_amount'] : 0;
                $subtotal += $totalPrice;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'variant_options' => $item['variant_options'] ?? $this->variantSnapshot($variant),
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'discount_amount' => $discountAmount,
                ];
                $products[] = ['product' => $product, 'variant' => $variant, 'quantity' => (int) $item['quantity']];
            }

            foreach ($products as $stockChange) {
                if ($stockChange['variant']) {
                    $stockChange['variant']->decrement('stock_quantity', $stockChange['quantity']);
                }
                $stockChange['product']->decrement('stock_quantity', $stockChange['quantity']);
            }

        // Use values from metadata (already calculated during payment initialization)
            $taxAmount = isset($metadata['tax_amount']) ? (float) $metadata['tax_amount'] : 0;
            $deliveryFee = isset($metadata['delivery_fee']) ? (float) $metadata['delivery_fee'] : 0;
            $totalAmount = isset($metadata['total_amount']) ? (float) $metadata['total_amount'] : ($subtotal + $taxAmount + $deliveryFee);

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $metadata['customer_id'],
                'branch_id' => $metadata['branch_id'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'delivery_address' => $metadata['delivery_address'],
                'delivery_city' => $metadata['delivery_city'] ?? null,
                'delivery_state' => $metadata['delivery_state'] ?? null,
                'delivery_longitude' => $metadata['delivery_longitude'] ?? null,
                'delivery_latitude' => $metadata['delivery_latitude'] ?? null,
                'delivery_phone' => $metadata['delivery_phone'] ?? null,
                'notes' => $metadata['notes'] ?? null,
                'payment_reference' => $reference,
                'payment_status' => 'paid',
            ]);

            foreach ($orderItems as $item) {
                $item['order_id'] = $order->id;
                OrderItem::create($item);
            }

            return ['order' => $order, 'created' => true];
        });

        if (isset($result['insufficient_stock'])) {
            return response()->json([
                'success' => false,
                'message' => $result['insufficient_stock'],
            ], 409);
        }

        $order = $result['order'];
        if (!$result['created']) {
            if ((string) $order->customer_id !== (string) $customer->getAuthIdentifier()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This payment does not belong to the authenticated customer.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment verified and order created successfully',
                'data' => $order->load(['orderItems.product']),
            ]);
        }

        // Load order relationships for email
        $order->load(['customer', 'orderItems.product', 'orderItems.variant']);

        // Fire OrderCreated event
        event(new OrderCreated($order));

        // Send order confirmation email to customer
        try {
            Mail::to($order->customer->email)->send(new OrderConfirmationEmail($order));
        } catch (\Exception $e) {
            Log::warning('Order confirmation email failed to send', [
                'order_id' => $order->id,
                'customer_email' => $order->customer->email,
                'error' => $e->getMessage()
            ]);
            // Don't fail order creation if email fails
        }

        // Notify admins + subadmins about the new order. Fan-out per recipient so a single
        // bad address doesn't drop the whole batch. Mail config is reloaded above by the
        // customer-confirmation block.
        $adminEmails = User::where('role', 'admin')->whereNotNull('email')->pluck('email');
        $subadminEmails = Subadmin::whereNotNull('email')->pluck('email');
        $recipients = $adminEmails->concat($subadminEmails)->unique()->filter()->values();
        foreach ($recipients as $recipientEmail) {
            try {
                Mail::to($recipientEmail)->send(new NewOrderForAdminEmail($order));
            } catch (\Exception $e) {
                Log::warning('Admin/subadmin new-order email failed to send', [
                    'order_id' => $order->id,
                    'recipient' => $recipientEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment verified and order created successfully',
            'data' => $order->load(['orderItems.product'])
        ]);
    }

    public function handlePaystackWebhook(Request $request, PaystackService $paystack)
    {
        $settings = cache()->rememberForever('app_settings', function () {
            return Setting::pluck('value', 'key')->toArray();
        });
        $secretKey = $settings['paystack_secret_key'] ?? '';

        // Verify Paystack signature for security
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();

        if (!$signature || $signature !== hash_hmac('sha512', $payload, $secretKey)) {
            Log::warning('Invalid Paystack signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        // Log the complete webhook payload for debugging
        Log::info('Paystack Webhook Received', [
            'event' => $event,
            'data' => $data,
            'headers' => $request->headers->all(),
            'timestamp' => now()->toISOString(),
        ]);

        // Handle different payment events
        if ($event === 'charge.success' && $data['status'] === 'success') {
            // Payment successful - create order
            Log::info('Processing successful payment', [
                'reference' => $data['reference'],
                'amount' => $data['amount'],
                'customer' => $data['customer'] ?? null,
                'authorization' => $data['authorization'] ?? null,
                'fees' => $data['fees'] ?? null,
                'channel' => $data['channel'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $reference = $data['reference'];

            // Ensure order not already created
            if (Order::where('payment_reference', $reference)->exists()) {
                return response()->json(['message' => 'Order already processed'], 200);
            }

            $metadata = $data['metadata'] ?? null;
            if (!$metadata) {
                return response()->json(['message' => 'Missing metadata'], 400);
            }

            $customerId = $metadata['customer_id'];
            $subtotal = 0;
            $orderItems = [];

            foreach ($metadata['items'] as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) continue;

                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : (float) $product->price;
                $totalPrice = isset($item['total_price']) ? (float) $item['total_price'] : $unitPrice * $item['quantity'];
                $discountAmount = isset($item['discount_amount']) ? (float) $item['discount_amount'] : 0;
                $subtotal += $totalPrice;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'discount_amount' => $discountAmount,
                ];

                // Reduce stock
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // Use values from metadata (already calculated during payment initialization)
            $taxAmount = isset($metadata['tax_amount']) ? floatval($metadata['tax_amount']) : 0;
            $deliveryFee = isset($metadata['delivery_fee']) ? floatval($metadata['delivery_fee']) : 0;
            $totalAmount = isset($metadata['total_amount']) ? floatval($metadata['total_amount']) : ($subtotal + $taxAmount + $deliveryFee);

            // Create order
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_id' => $customerId,
                'branch_id' => $metadata['branch_id'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'delivery_address' => $metadata['delivery_address'] ?? '',
                'delivery_city' => $metadata['delivery_city'] ?? null,
                'delivery_state' => $metadata['delivery_state'] ?? null,
                'delivery_longitude' => $metadata['delivery_longitude'] ?? null,
                'delivery_latitude' => $metadata['delivery_latitude'] ?? null,
                'delivery_phone' => $metadata['delivery_phone'] ?? '',
                'payment_reference' => $reference,
                'payment_status' => 'paid',
            ]);

            foreach ($orderItems as $item) {
                $item['order_id'] = $order->id;
                OrderItem::create($item);
            }

            // Load order relationships
            $order->load(['customer', 'orderItems.product']);

            // Fire OrderCreated event
            event(new OrderCreated($order));

            // Send order confirmation email to customer
            try {
                Mail::to($order->customer->email)->send(new OrderConfirmationEmail($order));
            } catch (\Exception $e) {
                Log::warning('Order confirmation email failed to send', [
                    'order_id' => $order->id,
                    'customer_email' => $order->customer->email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail order creation if email fails
            }

            // Notify admins + subadmins about the new order. Webhook is the
            // authoritative order-creation path (fires even when the customer
            // closes the browser before the redirect), so the email block from
            // verifyPayment is mirrored here.
            $adminEmails = User::where('role', 'admin')->whereNotNull('email')->pluck('email');
            $subadminEmails = Subadmin::whereNotNull('email')->pluck('email');
            $recipients = $adminEmails->concat($subadminEmails)->unique()->filter()->values();
            foreach ($recipients as $recipientEmail) {
                try {
                    Mail::to($recipientEmail)->send(new NewOrderForAdminEmail($order));
                } catch (\Exception $e) {
                    Log::warning('Admin/subadmin new-order email failed to send', [
                        'order_id' => $order->id,
                        'recipient' => $recipientEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create invoice
            $invoice = \App\Models\Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => \App\Models\Invoice::generateInvoiceNumber(),
                'paystack_reference' => $reference,
                'paystack_transaction_id' => $data['id'] ?? null,
                'amount' => $totalAmount,
                'currency' => 'NGN',
                'status' => 'paid',
                'payment_method' => $data['payment_method'] ?? null,
                'payment_channel' => $data['channel'] ?? null,
                'customer_email' => $data['customer']['email'] ?? null,
                'customer_phone' => $data['customer']['phone'] ?? null,
                'customer_address' => $metadata['delivery_address'] ?? '',
                'paystack_response' => $data,
                'paid_at' => now(),
            ]);

            // Create transaction detail
            $transactionDetail = \App\Models\TransactionDetail::create([
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'transaction_reference' => \App\Models\TransactionDetail::generateTransactionReference(),
                'paystack_reference' => $reference,
                'type' => 'payment',
                'status' => 'completed',
                'amount' => $totalAmount,
                'fee' => $data['fees'] ?? 0,
                'currency' => 'NGN',
                'payment_method' => $data['payment_method'] ?? null,
                'payment_channel' => $data['channel'] ?? null,
                'gateway_response' => 'success',
                'gateway_data' => $data,
                'description' => "Payment for order #{$order->order_number}",
                'initiated_by' => 'customer',
                'processed_at' => now(),
            ]);

            // Send notification to admins about successful payment and new order
            $this->notificationService->sendToAdmins(
                type: 'payment_success',
                title: 'Payment Successful - New Order',
                message: "Payment successful for order #{$order->order_number}. Amount: ₦{$order->total_amount}",
                data: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer->name,
                    'total_amount' => $order->total_amount,
                    'payment_reference' => $reference,
                ],
                options: [
                    'category' => 'payment',
                    'priority' => 'high',
                    'action_url' => "/admin/orders/{$order->id}",
                    'action_text' => 'View Order',
                    'metadata' => [
                        'payment_method' => $data['channel'] ?? 'unknown',
                        'gateway_response' => 'success',
                    ]
                ]
            );

            return response()->json(['message' => 'Order created successfully'], 200);
        }

        // Handle failed payments
        if ($event === 'charge.failed') {
            Log::info('Processing failed payment', [
                'reference' => $data['reference'],
                'amount' => $data['amount'],
                'customer' => $data['customer'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'channel' => $data['channel'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $reference = $data['reference'];
            $metadata = $data['metadata'] ?? null;
            
            if ($metadata) {
                $customerId = $metadata['customer_id'];
                $customer = \App\Models\Customer::find($customerId);
                
                if ($customer) {
                    // Create transaction detail for failed payment
                    $transactionDetail = \App\Models\TransactionDetail::create([
                        'transaction_reference' => \App\Models\TransactionDetail::generateTransactionReference(),
                        'paystack_reference' => $reference,
                        'type' => 'payment',
                        'status' => 'failed',
                        'amount' => $data['amount']/100,
                        'fee' => $data['fees'] ?? 0,
                        'currency' => 'NGN',
                        'payment_method' => $data['payment_method'] ?? null,
                        'payment_channel' => $data['channel'] ?? null,
                        'gateway_response' => $data['gateway_response'] ?? 'Payment failed',
                        'gateway_data' => $data,
                        'description' => "Failed payment attempt for customer {$customer->name}",
                        'initiated_by' => 'customer',
                        'processed_at' => now(),
                    ]);

                    // Send notification to admins about failed payment
                    $this->notificationService->sendToAdmins(
                        type: 'payment_failed',
                        title: 'Payment Failed - Action Required',
                        message: "Payment failed for customer {$customer->name}. Reference: {$reference}. Amount: ₦" . ($data['amount']/100),
                        data: [
                            'customer_id' => $customerId,
                            'customer_name' => $customer->name,
                            'amount' => $data['amount']/100,
                            'failure_reason' => $data['gateway_response'] ?? 'Unknown',
                        ],
                        options: [
                            'category' => 'payment',
                            'priority' => 'critical',
                            'action_url' => "/admin/customers/{$customerId}",
                            'action_text' => 'View Customer',
                            'metadata' => [
                                'payment_method' => $data['channel'] ?? 'unknown',
                                'gateway_response' => $data['gateway_response'] ?? 'Unknown error',
                                'reference' => $reference,
                            ]
                        ]
                    );
                }
            }

            return response()->json(['message' => 'Payment failure notification sent'], 200);
        }

        // Handle pending payments
        if ($event === 'charge.pending') {
            Log::info('Processing pending payment', [
                'reference' => $data['reference'],
                'amount' => $data['amount'],
                'customer' => $data['customer'] ?? null,
                'channel' => $data['channel'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $reference = $data['reference'];
            $metadata = $data['metadata'] ?? null;
            
            if ($metadata) {
                $customerId = $metadata['customer_id'];
                $customer = \App\Models\Customer::find($customerId);
                
                if ($customer) {
                    // Create transaction detail for pending payment
                    $transactionDetail = \App\Models\TransactionDetail::create([
                        'transaction_reference' => \App\Models\TransactionDetail::generateTransactionReference(),
                        'paystack_reference' => $reference,
                        'type' => 'payment',
                        'status' => 'pending',
                        'amount' => $data['amount']/100,
                        'fee' => $data['fees'] ?? 0,
                        'currency' => 'NGN',
                        'payment_method' => $data['payment_method'] ?? null,
                        'payment_channel' => $data['channel'] ?? null,
                        'gateway_response' => 'Payment pending',
                        'gateway_data' => $data,
                        'description' => "Pending payment for customer {$customer->name}",
                        'initiated_by' => 'customer',
                        'processed_at' => null, // Not processed yet
                    ]);

                    // Send notification to admins about pending payment
                    $this->notificationService->sendToAdmins(
                        type: 'payment_pending',
                        title: 'Payment Pending',
                        message: "Payment is pending for customer {$customer->name}. Reference: {$reference}. Amount: ₦" . ($data['amount']/100),
                        data: [
                            'customer_id' => $customerId,
                            'customer_name' => $customer->name,
                            'amount' => $data['amount']/100,
                        ],
                        options: [
                            'category' => 'payment',
                            'priority' => 'medium',
                            'action_url' => "/admin/customers/{$customerId}",
                            'action_text' => 'View Customer',
                            'metadata' => [
                                'payment_method' => $data['channel'] ?? 'unknown',
                                'reference' => $reference,
                            ]
                        ]
                    );
                }
            }

            return response()->json(['message' => 'Payment pending notification sent'], 200);
        }

        // Log any unhandled events
        Log::info('Unhandled Paystack webhook event', [
            'event' => $event,
            'data' => $data,
        ]);

        return response()->json(['message' => 'Event ignored'], 200);
    }

    public function initializePayment(CreateOrderRequest $request, PaystackService $paystack)
    {
        $data = $request->validated();
        $customer = Auth::guard('customer-api')->user();

        // Fetch settings for delivery_fee only. Tax is per-product (product.tax_rate); VAT is not used.
        $settings = cache()->rememberForever('app_settings', function () {
            return Setting::pluck('value', 'key')->toArray();
        });

        // Use delivery_fee from request if provided, otherwise use settings default
        $deliveryFee = isset($data['delivery_fee']) 
            ? floatval($data['delivery_fee']) 
            : (isset($settings['delivery_fee']) ? floatval($settings['delivery_fee']) : 0);

        // Calculate totals with ad discount applied per item and tax per product (product.tax_rate)
        $subtotal = 0;
        $taxAmount = 0;
        $orderItems = [];
        foreach ($data['items'] as $itemIndex => $item) {
            $product = Product::findOrFail($item['product_id']);
            $variant = $this->resolveOrderVariant($product, $item, $itemIndex);
            $availableStock = $variant?->stock_quantity ?? $product->stock_quantity;
            if ($availableStock < $item['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for product: {$product->name}. Only {$availableStock} units available.",
                ], 400);
            }

            $originalPrice = (float) ($variant?->price ?? $product->price);
            $unitPrice = $product->getUnitPriceWithAdDiscount($variant ? (float) $variant->price : null);
            $discountPerUnit = $originalPrice - $unitPrice;
            $totalPrice = $unitPrice * $item['quantity'];
            $discountAmount = $discountPerUnit * $item['quantity'];
            $subtotal += $totalPrice;

            // Tax for this line using this product's tax_rate only (no global VAT).
            $productTaxRate = isset($product->tax_rate) ? (float) $product->tax_rate / 100 : 0;
            $lineTax = round($totalPrice * $productTaxRate, 2);
            $taxAmount += $lineTax;

            $orderItems[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'variant_options' => $this->variantSnapshot($variant),
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'discount_amount' => $discountAmount,
                'tax_amount' => $lineTax,
            ];
        }

        $taxAmount = round($taxAmount, 2);
        $totalAmount = round($subtotal + $taxAmount + $deliveryFee, 2);

        // Initialize Paystack payment (amount = subtotal + tax + delivery_fee)
        $payment = $paystack->initializePayment($customer->email, $totalAmount, [
            'customer_id' => $customer->id,
            'branch_id' => $data['branch_id'],
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $totalAmount,
            'delivery_address' => $data['delivery_address'],
            'delivery_phone' => $data['delivery_phone'],
            'notes' => $data['notes'] ?? null,
            'items' => $orderItems,
        ]);

        if (!isset($payment['status']) || !$payment['status']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment',
                'data' => $payment
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized',
            'data' => [
                'authorization_url' => $payment['data']['authorization_url'],
                'reference' => $payment['data']['reference']
            ]
        ]);
    }

    /**
     * Get customer orders
     */
    public function getOrders(Request $request): JsonResponse
    {
        $customer = $request->user();
        $orders = Order::where('customer_id', $customer->id)
            ->with(['branch', 'rider', 'orderItems.product', 'orderItems.variant'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $orders->map(fn (Order $order) => $this->formatOrderForResponse($order))->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get order by ID
     */
    public function getOrder(Request $request, string $id): JsonResponse
    {
        $customer = Auth::guard('customer-api')->user();
        $order = Order::where('customer_id', $customer->id)
            ->with(['branch', 'rider', 'orderItems.product'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrderForResponse($order),
        ]);
    }

    /**
     * Format order for API response: ensure discounted unit_price is shown, with delivery_fee and tax_amount from order.
     */
    private function formatOrderForResponse(Order $order): array
    {
        $order->loadMissing(['branch', 'rider', 'orderItems.product', 'orderItems.variant']);
        $data = $order->toArray();
        $data['delivery_fee'] = (float) $order->delivery_fee;
        $data['tax_amount'] = (float) $order->tax_amount;
        $data['subtotal'] = (float) $order->subtotal;
        $data['total_amount'] = (float) $order->total_amount;
        $data['order_items'] = collect($order->orderItems)->map(function (OrderItem $item) {
            $product = $item->product;
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'variant_options' => $item->variant_options,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'original_unit_price' => $product ? (float) $product->price : (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'discount_amount' => (float) ($item->discount_amount ?? 0),
                'product' => $product ? $product->only(['id', 'name', 'sku', 'image', 'price']) : null,
            ];
        })->values()->all();
        return $data;
    }

    private function resolveOrderVariant(Product $product, array $item, int $itemIndex): ?ProductVariant
    {
        $hasVariants = $product->variants()->exists();
        $variantId = $item['variant_id'] ?? $item['product_variant_id'] ?? null;

        if (!$variantId && $hasVariants) {
            throw ValidationException::withMessages([
                "items.$itemIndex.variant_id" => ['Choose a color, size, and dimension for this product.'],
            ]);
        }

        if (!$variantId) {
            return null;
        }

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->find($variantId);

        if (!$variant) {
            throw ValidationException::withMessages([
                "items.$itemIndex.variant_id" => ['The selected option is not available for this product.'],
            ]);
        }

        return $variant;
    }

    private function variantSnapshot(?ProductVariant $variant): ?array
    {
        if (!$variant) {
            return null;
        }

        return array_filter([
            'sku' => $variant->sku,
            'color' => $variant->color,
            'size' => $variant->size,
            'dimensions' => $variant->dimensions,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function attachVariantDisplayPrices(Product $product): void
    {
        foreach ($product->variants as $variant) {
            $variant->setAttribute(
                'display_price',
                $product->getUnitPriceWithAdDiscount((float) $variant->price)
            );
        }
    }

    public function createWhishList(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer-api')->user();
        $data = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
        ]);

        $existing = \App\Models\WishList::where('user_id', $customer->id)
            ->where('product_id', $data['product_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Product already in wishlist',
            ], 400);
        }

        $wishlist = \App\Models\WishList::create([
            'user_id' => $customer->id,
            'product_id' => $data['product_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to wishlist',
            'data' => $wishlist,
        ], 201);
    }

    public function getWhishList(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer-api')->user();
        $wishlist = \App\Models\WishList::where('user_id', $customer->id)
            ->with(['product' => fn ($q) => $q->with('ads')])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $wishlist,
        ]);
    }

    public function deleteWhishList(Request $request, string $id): JsonResponse
    {
        $customer = Auth::guard('customer-api')->user();
        $wishlistItem = \App\Models\WishList::where('user_id', $customer->id)
            ->where('id', $id)
            ->firstOrFail();

        $wishlistItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed from wishlist',
        ]);
    }

    public function getProfile(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer-api')->user();

        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    /**
     * Update customer profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var \App\Models\Customer $customer */
        $customer = Auth::guard('customer-api')->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $data = $request->validated();

        // Handle password change
        if ($request->has('password')) {
            // Verify current password
            if (!Hash::check($request->current_password, $customer->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 422);
            }
            
            // Hash the new password
            $data['password'] = Hash::make($data['password']);
            
            // Remove password confirmation fields from data
            unset($data['current_password'], $data['password_confirmation']);
        } else {
            // Remove password fields if not changing password
            unset($data['current_password'], $data['password'], $data['password_confirmation']);
        }

        try {
            $customer->update($data);
            $customer->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $customer,
            ]);
        } catch (\Exception $e) {
            Log::error('Customer profile update failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile. Please try again.',
            ], 500);
        }
    }

    /**
     * Get recently viewed products (Public - not user-specific)
     */
    public function getRecentlyViewed(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 20);
            
            $recentlyViewed = \App\Models\RecentlyViewedProduct::with(['product' => fn ($q) => $q->with('ads')])
                ->orderBy('viewed_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $recentlyViewed,
                'count' => $recentlyViewed->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch recently viewed products', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recently viewed products'
            ], 500);
        }
    }

    /**
     * Mark a product as viewed (Public - not user-specific)
     */
    public function markProductViewed(string $productId): JsonResponse
    {
        try {
            // Check if product exists and is active
            $product = \App\Models\Product::where('id', $productId)
                ->where('is_active', true)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found or inactive'
                ], 404);
            }

            // Use updateOrCreate to prevent duplicates
            // For public routes, we only track by product_id without customer_id
            $recentlyViewed = \App\Models\RecentlyViewedProduct::updateOrCreate(
                [
                    'customer_id' => null,
                    'product_id' => $productId
                ],
                [
                    'viewed_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Product marked as viewed',
                'data' => [
                    'product_id' => $productId,
                    'viewed_at' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark product as viewed', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark product as viewed'
            ], 500);
        }
    }

    /**
     * Update customer profile picture
     */
    public function updateProfilePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        /** @var \App\Models\Customer $customer */
        $customer = Auth::guard('customer-api')->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            // Delete old profile picture if it exists
            if (!empty($customer->profile_picture)) {
                $oldPath = str_replace('/storage/', '', $customer->profile_picture);
                Storage::disk('public')->delete($oldPath);
            }

            // Handle new profile picture upload
            $file = $request->file('profile_picture');
            $extension = $file->getClientOriginalExtension();
            $filename = $customer->id . '.' . $extension;
            $path = $file->storeAs('profiles/customers', $filename, 'public');
            
            // Update customer profile picture
            $customer->update([
                'profile_picture' => Storage::url($path)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'data' => [
                    'profile_picture' => $customer->profile_picture
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Customer profile picture update failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile picture. Please try again.',
            ], 500);
        }
    }
}
