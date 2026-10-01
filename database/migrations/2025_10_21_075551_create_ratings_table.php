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
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // foreign UUID keys
            $table->uuid('rider_id');
            $table->uuid('customer_id');
            $table->uuid('order_id')->nullable();

            $table->unsignedTinyInteger('rating')->comment('1–5 stars');
            $table->text('comment')->nullable();
            $table->timestamps();

            // correct foreign key constraints
            $table->foreign('rider_id')->references('id')->on('riders')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
