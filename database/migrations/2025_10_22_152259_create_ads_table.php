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
        Schema::create('ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['image', 'video']);
            $table->string('media_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('link_url')->nullable();
            $table->enum('position', ['banner', 'sidebar', 'popup', 'in_content'])->default('banner');
            $table->enum('status', ['active', 'inactive', 'scheduled'])->default('inactive');
            $table->integer('priority')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('impressions')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['status', 'position']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};