<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subadmins', function (Blueprint $table) {
            if (!Schema::hasColumn('subadmins', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('subadmins', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('subadmins', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('subadmins', 'address')) {
                $table->string('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('subadmins', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (!Schema::hasColumn('subadmins', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
            if (!Schema::hasColumn('subadmins', 'postal_code')) {
                $table->string('postal_code')->nullable()->after('state');
            }
            if (!Schema::hasColumn('subadmins', 'bio')) {
                $table->text('bio')->nullable()->after('postal_code');
            }
            if (!Schema::hasColumn('subadmins', 'profile_picture')) {
                $table->string('profile_picture')->nullable()->after('bio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subadmins', function (Blueprint $table) {
            if (Schema::hasColumn('subadmins', 'first_name')) {
                $table->dropColumn('first_name');
            }
            if (Schema::hasColumn('subadmins', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('subadmins', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('subadmins', 'address')) {
                $table->dropColumn('address');
            }
            if (Schema::hasColumn('subadmins', 'city')) {
                $table->dropColumn('city');
            }
            if (Schema::hasColumn('subadmins', 'state')) {
                $table->dropColumn('state');
            }
            if (Schema::hasColumn('subadmins', 'postal_code')) {
                $table->dropColumn('postal_code');
            }
            if (Schema::hasColumn('subadmins', 'bio')) {
                $table->dropColumn('bio');
            }
            if (Schema::hasColumn('subadmins', 'profile_picture')) {
                $table->dropColumn('profile_picture');
            }
        });
    }
};


