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
        Schema::create('hive_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User who performed the action
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('cascade'); // User who receives the activity notification
            $table->string('type'); // comment, share, mention, etc.
            $table->string('action_text')->nullable(); // "commented on your post", etc.
            $table->morphs('target'); // The target object (post, comment, etc.)
            $table->json('metadata')->nullable(); // Additional data about the activity
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hive_activities');
    }
};
