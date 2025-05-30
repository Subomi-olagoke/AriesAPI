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
        if (!Schema::hasTable('hive_activities')) {
            Schema::create('hive_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id'); // User who performed the action
                $table->unsignedBigInteger('target_user_id')->nullable(); // User who receives the activity notification
                $table->string('type'); // comment, share, mention, etc.
                $table->string('action_text')->nullable(); // "commented on your post", etc.
                $table->morphs('target'); // The target object (post, comment, etc.)
                $table->json('metadata')->nullable(); // Additional data about the activity
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hive_activities');
    }
};
