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
        if (!Schema::hasTable('hive_community_members')) {
            Schema::create('hive_community_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('community_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role')->default('member'); // member, moderator, admin
                $table->string('status')->default('active'); // active, pending, banned
                $table->boolean('notifications_enabled')->default(true);
                $table->timestamp('joined_at')->useCurrent();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();
                
                // Make sure a user can only be a member of a community once
                $table->unique(['community_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hive_community_members');
    }
};
