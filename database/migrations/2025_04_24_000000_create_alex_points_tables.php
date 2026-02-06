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
        // Add points column to users table
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('alex_points')->default(0);
            $table->unsignedInteger('point_level')->default(1);
            $table->unsignedBigInteger('points_to_next_level')->default(100);
        });

        // Create points transaction history table
        Schema::create('alex_points_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->integer('points')->default(0); // Can be positive or negative
            $table->string('action_type'); // e.g., post_created, comment_added, course_completed
            $table->string('reference_type')->nullable(); // The model type that the points are related to
            $table->string('reference_id')->nullable(); // The ID of the related model
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();
        });

        // Create points rule table
        Schema::create('alex_points_rules', function (Blueprint $table) {
            $table->id();
            $table->string('action_type')->unique(); // The action that triggers points
            $table->integer('points'); // Points awarded for this action
            $table->string('description'); // Description of the rule
            $table->boolean('is_active')->default(true);
            $table->boolean('is_one_time')->default(false); // Whether this can only be awarded once
            $table->integer('daily_limit')->default(0); // Maximum times per day this can be awarded (0 = unlimited)
            $table->json('metadata')->nullable(); // Additional settings for the rule
            $table->timestamps();
        });

        // Create level thresholds table
        Schema::create('alex_points_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('level');
            $table->unsignedBigInteger('points_required');
            $table->string('name')->nullable(); // Optional level name
            $table->text('description')->nullable();
            $table->json('rewards')->nullable(); // Rewards for reaching this level
            $table->timestamps();
            
            $table->unique('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alex_points_transactions');
        Schema::dropIfExists('alex_points_rules');
        Schema::dropIfExists('alex_points_levels');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['alex_points', 'point_level', 'points_to_next_level']);
        });
    }
};