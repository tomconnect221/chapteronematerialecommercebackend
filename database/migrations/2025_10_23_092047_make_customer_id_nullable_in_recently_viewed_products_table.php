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
        Schema::table('recently_viewed_products', function (Blueprint $table) {
            // Make customer_id nullable for public tracking
            $table->uuid('customer_id')->nullable()->change();
            
            // Drop the unique constraint that includes customer_id
            $table->dropUnique(['customer_id', 'product_id']);
            
            // Add a new unique constraint for customer-specific entries only
            $table->unique(['customer_id', 'product_id'], 'unique_customer_product_view');
            
            // Update the foreign key constraint to allow nulls
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recently_viewed_products', function (Blueprint $table) {
            // Revert customer_id to not nullable
            $table->uuid('customer_id')->nullable(false)->change();
            
            // Drop the new unique constraint
            $table->dropUnique('unique_customer_product_view');
            
            // Restore the original unique constraint
            $table->unique(['customer_id', 'product_id']);
            
            // Update foreign key constraint
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }
};