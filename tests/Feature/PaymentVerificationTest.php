<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Models\Customer;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_payment_requires_a_reference(): void
    {
        $this->withoutMiddleware()
            ->postJson('/api/customer/verify-payment')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference');
    }

    public function test_incomplete_successful_paystack_response_does_not_cause_server_error(): void
    {
        $paystack = \Mockery::mock(PaystackService::class);
        $paystack->shouldReceive('verifyPayment')
            ->once()
            ->with('TEST-PAYMENT-REF-001')
            ->andReturn([
                'status' => true,
                'data' => ['status' => 'success'],
            ]);
        $this->app->instance(PaystackService::class, $paystack);

        $this->withoutMiddleware()
            ->postJson('/api/customer/verify-payment', [
                'reference' => 'TEST-PAYMENT-REF-001',
            ])
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Payment was received, but its order details are incomplete. Please contact support.',
            ]);
    }

    public function test_legacy_payment_metadata_without_delivery_coordinates_can_create_an_order(): void
    {
        Event::fake([OrderCreated::class]);
        Mail::fake();

        $customerId = (string) Str::uuid();
        $branchId = (string) Str::uuid();
        $categoryId = (string) Str::uuid();
        $productId = (string) Str::uuid();

        DB::table('customers')->insert([
            'id' => $customerId,
            'name' => 'Test Customer',
            'email' => 'customer@example.test',
            'password' => 'hashed-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('branches')->insert([
            'id' => $branchId,
            'name' => 'Main branch',
            'address' => 'Test address',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('categories')->insert([
            'id' => $categoryId,
            'name' => 'Stationery',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'id' => $productId,
            'name' => 'Test notebook',
            'price' => 9000,
            'sku' => 'PAYMENT-TEST-001',
            'stock_quantity' => 2,
            'category_id' => $categoryId,
            'branch_id' => $branchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = Customer::findOrFail($customerId);
        $this->actingAs($customer, 'customer-api');

        $paystack = \Mockery::mock(PaystackService::class);
        $paystack->shouldReceive('verifyPayment')
            ->once()
            ->with('LEGACY-PAYMENT-REF-001')
            ->andReturn([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'metadata' => [
                        'customer_id' => $customerId,
                        'branch_id' => $branchId,
                        'items' => [
                            ['product_id' => $productId, 'quantity' => 1, 'unit_price' => 9000, 'total_price' => 9000],
                        ],
                        'delivery_address' => 'Ilorin, Kwara, Nigeria',
                        'total_amount' => 9000,
                    ],
                ],
            ]);
        $this->app->instance(PaystackService::class, $paystack);

        $this->postJson('/api/customer/verify-payment', [
            'reference' => 'LEGACY-PAYMENT-REF-001',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'payment_reference' => 'LEGACY-PAYMENT-REF-001',
            'delivery_address' => 'Ilorin, Kwara, Nigeria',
            'delivery_city' => null,
            'delivery_state' => null,
            'delivery_longitude' => null,
            'delivery_latitude' => null,
        ]);
    }
}
