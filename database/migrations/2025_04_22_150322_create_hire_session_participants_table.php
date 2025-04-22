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
        Schema::create('hire_session_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hire_session_id');
            $table->uuid('user_id');
            $table->string('role')->default('participant'); // participant or moderator
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->json('preferences')->nullable();
            $table->string('connection_quality')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('hire_session_id')->references('id')->on('hire_sessions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Prevent duplicate participations
            $table->unique(['hire_session_id', 'user_id', 'left_at'], 'unique_active_participation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hire_session_participants');
    }
};
