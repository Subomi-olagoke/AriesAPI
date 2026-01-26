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
        Schema::table('users', function (Blueprint $table) {
            // Check if columns don't already exist
            if (!Schema::hasColumn('users', 'device_token')) {
                $table->string('device_token', 255)->nullable()->after('apple_id');
            }
            if (!Schema::hasColumn('users', 'device_type')) {
                $table->string('device_type', 20)->nullable()->after('device_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'device_token')) {
                $table->dropColumn('device_token');
            }
            if (Schema::hasColumn('users', 'device_type')) {
                $table->dropColumn('device_type');
            }
        });
    }
};
