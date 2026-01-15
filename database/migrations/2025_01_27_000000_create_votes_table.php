<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create votes table for Reddit-style voting
     */
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id'); // UUID to match users table
            $table->morphs('voteable'); // voteable_id and voteable_type
            $table->enum('vote_type', ['up', 'down']); // upvote or downvote
            $table->timestamps();
            
            // Ensure one vote per user per content item
            $table->unique(['user_id', 'voteable_id', 'voteable_type']);
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for performance
            $table->index(['voteable_type', 'voteable_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};

