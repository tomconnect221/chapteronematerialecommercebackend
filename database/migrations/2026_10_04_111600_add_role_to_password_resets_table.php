<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_resets', function (Blueprint $table): void {
            $table->string('role', 32)->nullable()->after('email');
            $table->unique(['email', 'role'], 'password_resets_email_role_unique');
        });
    }

    public function down(): void
    {
        Schema::table('password_resets', function (Blueprint $table): void {
            $table->dropUnique('password_resets_email_role_unique');
            $table->dropColumn('role');
        });
    }
};
