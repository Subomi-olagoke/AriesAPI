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
            $table->uuid('id')->primary();
            $table->uuid('notifiable_id'); // Add the notifiable_id column
            $table->string('notifiable_type'); // Add the notifiable_type column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('notifiable_id'); // Remove the notifiable_id column
        $table->dropColumn('notifiable_type'); // Remove the notifiable_type column
        });
    }
};
