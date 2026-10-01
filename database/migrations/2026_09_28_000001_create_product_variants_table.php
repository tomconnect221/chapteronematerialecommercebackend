<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('sku', 100)->nullable()->unique();
            $table->string('color', 60)->nullable();
            $table->string('size', 50)->nullable();
            $table->string('dimensions', 100)->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'stock_quantity']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->uuid('product_variant_id')->nullable()->after('product_id');
            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product_variants')
                ->nullOnDelete();
            $table->json('variant_options')->nullable()->after('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'variant_options']);
        });

        Schema::dropIfExists('product_variants');
    }
};