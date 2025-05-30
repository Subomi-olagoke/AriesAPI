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
        if (!Schema::hasTable('hive_channels')) {
            Schema::create('hive_channels', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('color', 7)->default('#007AFF');
                $table->unsignedBigInteger('creator_id');
                // We'll add the foreign key reference but without strict enforcement
                // in case there's a mismatch with the users table structure
                $table->string('privacy')->default('public'); // public, private
                $table->string('status')->default('active'); // active, archived
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hive_channels');
    }
};
