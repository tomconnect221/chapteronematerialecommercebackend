<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_city', 100)->after('delivery_address');
            $table->string('delivery_state', 100)->after('delivery_city');
            $table->decimal('delivery_longitude', 11, 8)->after('delivery_state');
            $table->decimal('delivery_latitude', 10, 8)->after('delivery_longitude');
            $table->string('delivery_phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_city', 'delivery_state', 'delivery_longitude', 'delivery_latitude']);
            $table->string('delivery_phone')->nullable(false)->change();
        });
    }
};
