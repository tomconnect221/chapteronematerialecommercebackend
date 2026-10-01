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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_ad')->default(false)->after('is_featured');
            $table->string('ad_discount_type', 20)->nullable()->after('is_ad'); // 'percent' or 'fixed'
            $table->decimal('ad_discount_value', 10, 2)->nullable()->after('ad_discount_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_ad', 'ad_discount_type', 'ad_discount_value']);
        });
    }
};
