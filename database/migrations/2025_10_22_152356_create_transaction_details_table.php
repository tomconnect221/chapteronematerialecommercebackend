<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('invoice_id')->nullable();
            $table->string('transaction_reference')->unique();
            $table->string('paystack_reference')->nullable();
            $table->enum('type', ['payment', 'refund', 'chargeback', 'adjustment'])->default('payment');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->virtualAs('amount - fee');
            $table->string('currency', 3)->default('NGN');
            $table->string('payment_method')->nullable();
            $table->string('payment_channel')->nullable();
            $table->string('gateway_response')->nullable();
            $table->json('gateway_data')->nullable();
            $table->text('description')->nullable();
            $table->string('initiated_by')->nullable(); // user_id who initiated
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            
            // Indexes for better performance
            $table->index(['status', 'type']);
            $table->index(['order_id', 'type']);
            $table->index('transaction_reference');
            $table->index('paystack_reference');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_details');
    }
};