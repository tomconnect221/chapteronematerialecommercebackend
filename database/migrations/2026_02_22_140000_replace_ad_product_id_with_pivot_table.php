<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for ad-product many-to-many
        Schema::create('ad_product', function (Blueprint $table) {
            $table->uuid('ad_id');
            $table->uuid('product_id');
            $table->primary(['ad_id', 'product_id']);
            $table->foreign('ad_id')->references('id')->on('ads')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // Migrate existing product_id from ads into pivot
        if (Schema::hasColumn('ads', 'product_id')) {
            $adsWithProduct = DB::table('ads')->whereNotNull('product_id')->get(['id', 'product_id']);
            foreach ($adsWithProduct as $row) {
                DB::table('ad_product')->insert([
                    'ad_id' => $row->id,
                    'product_id' => $row->product_id,
                ]);
            }
            Schema::table('ads', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->uuid('product_id')->nullable()->after('discount_value');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        // Copy first product from pivot back to ads (best-effort)
        $pivot = DB::table('ad_product')->get();
        foreach ($pivot as $row) {
            DB::table('ads')->where('id', $row->ad_id)->update(['product_id' => $row->product_id]);
        }

        Schema::dropIfExists('ad_product');
    }
};
