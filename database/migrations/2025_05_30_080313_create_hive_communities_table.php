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
        if (!Schema::hasTable('hive_communities')) {
            Schema::create('hive_communities', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('avatar')->nullable();
                $table->string('privacy')->default('public'); // public, private, invite-only
                $table->unsignedBigInteger('creator_id');
                $table->integer('member_count')->default(0);
                $table->string('status')->default('active'); // active, archived
                $table->string('join_code')->nullable()->unique(); // For invitation links
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hive_communities');
    }
};
