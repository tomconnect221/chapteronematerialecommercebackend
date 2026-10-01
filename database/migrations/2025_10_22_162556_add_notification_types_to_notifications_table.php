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
        Schema::table('notifications', function (Blueprint $table) {
            // Add new columns for enhanced notification system
            $table->enum('category', ['order', 'inventory', 'payment', 'system', 'general'])->default('general')->after('type');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->after('category');
            $table->string('action_url')->nullable()->after('priority'); // URL for action button
            $table->string('action_text')->nullable()->after('action_url'); // Text for action button
            $table->json('metadata')->nullable()->after('data'); // Additional metadata for specific notification types
            $table->timestamp('expires_at')->nullable()->after('read_at'); // Auto-expire notifications
            
            // Add indexes for better performance
            $table->index(['category', 'priority']);
            $table->index(['user_type', 'is_read']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['category', 'priority']);
            $table->dropIndex(['user_type', 'is_read']);
            $table->dropIndex(['expires_at']);
            
            $table->dropColumn([
                'category',
                'priority',
                'action_url',
                'action_text',
                'metadata',
                'expires_at'
            ]);
        });
    }
};