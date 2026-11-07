<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Mail\NewOrderForAdminEmail;
use App\Mail\OrderConfirmationEmail;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Subadmin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaystackWebhookController extends Controller
{
    /**
     * Handle Paystack webhook events.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($request)) {
                Log::warning('Invalid Paystack webhook signature', [
                    'headers' => $request->headers->all(),
                    'body' => $request->all()
                ]);
                return response()->json(['message' => 'Invalid signature'], 400);
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

            switch ($event) {
                case 'charge.success':
                    $this->handleChargeSuccess($data);
                    break;
                case 'charge.failed':
                    $this->handleChargeFailed($data);
                    break;
                case 'transfer.success':
                    $this->handleTransferSuccess($data);
                    break;
                case 'transfer.failed':
                    $this->handleTransferFailed($data);
                    break;
                default:
                    Log::info('Unhandled Paystack webhook event', ['event' => $event]);
            }

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            Log::error('Paystack webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle successful charge.
     */
    private function handleChargeSuccess(array $data): void
    {
        DB::transaction(function () use ($data) {
            $reference = $data['reference'];
            
            // Check if order already exists
            if (Order::where('payment_reference', $reference)->exists()) {
                Log::info('Order already exists for reference', ['reference' => $reference]);
                return;
            }

            $metadata = $data['metadata'] ?? null;
            if (!$metadata) {
                Log::warning('No metadata found in webhook data', ['reference' => $reference]);
                return;
            }

            Log::info('Processing successful payment', [
                'reference' => $reference,
                'amount' => $data['amount'],
                'customer' => $data['customer'] ?? null,
                'authorization' => $data['authorization'] ?? null,
                'fees' => $data['fees'] ?? null,
                'channel' => $data['channel'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'metadata' => $metadata,
            ]);

            $customerId = $metadata['customer_id'];
            $subtotal = 0;
            $orderItems = [];

            foreach ($metadata['items'] as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                if (!$product) continue;

                $variantId = $item['product_variant_id'] ?? $item['variant_id'] ?? null;
                $variant = $variantId
                    ? ProductVariant::where('product_id', $product->id)->find($variantId)
                    : null;
                if ($variantId && !$variant) {
                    throw new \RuntimeException('The purchased product option no longer exists.');
                }
                if ($variant && $variant->stock_quantity < $item['quantity']) {
                    throw new \RuntimeException('The purchased product option is out of stock.');
                }

                // Use stored prices from metadata (discounted unit_price, total_price) from payment init
                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : (float) $product->price;
                $totalPrice = isset($item['total_price']) ? (float) $item['total_price'] : $unitPrice * $item['quantity'];
                $discountAmount = isset($item['discount_amount']) ? (float) $item['discount_amount'] : 0;
                $subtotal += $totalPrice;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'variant_options' => $item['variant_options'] ?? array_filter([
                        'sku' => $variant?->sku,
                        'color' => $variant?->color,
                        'size' => $variant?->size,
                        'dimensions' => $variant?->dimensions,
                    ], fn ($value) => $value !== null && $value !== ''),
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'discount_amount' => $discountAmount,
                ];

                if ($variant) {
                    $variant->decrement('stock_quantity', $item['quantity']);
                }
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // Use tax and delivery from metadata (calculated with per-product tax at payment init). No VAT from settings.
            $taxAmount = isset($metadata['tax_amount']) ? floatval($metadata['tax_amount']) : 0;
            $deliveryFee = isset($metadata['delivery_fee']) ? floatval($metadata['delivery_fee']) : 0;
            $totalAmount = isset($metadata['total_amount']) ? floatval($metadata['total_amount']) : round($subtotal + $taxAmount + $deliveryFee, 2);

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
                \App\Models\OrderItem::create($item);
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

            // Notify admins + subadmins about the new order. This is the active
            // webhook route (routes/api.php), so the fan-out lives here too —
            // mirrors verifyPayment so the email fires regardless of which path
            // creates the order. Per-recipient try/catch so one bad address
            // doesn't drop the whole batch.
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
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
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
            try {
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
                
                Log::info('Transaction detail created successfully', [
                    'transaction_id' => $transactionDetail->id,
                    'transaction_reference' => $transactionDetail->transaction_reference,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create transaction detail', [
                    'error' => $e->getMessage(),
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                ]);
                throw $e; // Re-throw to rollback the transaction
            }

            Log::info('Order and invoice created successfully', [
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'transaction_id' => $transactionDetail->id,
                'amount' => $totalAmount,
            ]);
        });
    }

    /**
     * Handle failed charge.
     */
    private function handleChargeFailed(array $data): void
    {
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
                // Create a dummy order for failed payments to satisfy foreign key constraint
                $dummyOrder = Order::create([
                    'order_number' => Order::generateOrderNumber(),
                    'customer_id' => $customerId,
                    'branch_id' => $metadata['branch_id'] ?? null,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'delivery_fee' => 0,
                    'total_amount' => 0,
                    'delivery_address' => $metadata['delivery_address'] ?? '',
                    'delivery_phone' => $metadata['delivery_phone'] ?? '',
                    'payment_reference' => $reference,
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                ]);

                // Create transaction detail for failed payment
                $transactionDetail = \App\Models\TransactionDetail::create([
                    'order_id' => $dummyOrder->id,
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

                Log::info('Failed payment transaction recorded', [
                    'order_id' => $dummyOrder->id,
                    'transaction_id' => $transactionDetail->id,
                    'amount' => $data['amount']/100,
                ]);
            }
        }
    }

    /**
     * Handle successful transfer.
     */
    private function handleTransferSuccess(array $data): void
    {
        Log::info('Transfer successful', [
            'transfer_code' => $data['transfer_code'] ?? null,
            'amount' => $data['amount'] ?? null,
        ]);
    }

    /**
     * Handle failed transfer.
     */
    private function handleTransferFailed(array $data): void
    {
        Log::info('Transfer failed', [
            'transfer_code' => $data['transfer_code'] ?? null,
            'reason' => $data['reason'] ?? 'Unknown error',
        ]);
    }

    /**
     * Verify Paystack webhook signature.
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Paystack-Signature');
        
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        
        // Get secret key from settings (same as PaystackService)
        $settings = cache()->rememberForever('app_settings', function () {
            return \App\Models\Setting::pluck('value', 'key')->toArray();
        });
        
        $secret = $settings['paystack_secret_key'] ?? '';
        
        if (empty($secret)) {
            Log::warning('Paystack secret key not configured');
            return false;
        }
        
        $expectedSignature = hash_hmac('sha512', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
}