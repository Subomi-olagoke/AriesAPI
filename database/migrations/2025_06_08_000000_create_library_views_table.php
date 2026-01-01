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
        Schema::create('library_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->unsignedBigInteger('library_id');
            $table->timestamp('viewed_at');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('library_id')->references('id')->on('open_libraries')->onDelete('cascade');
            
            // Index for efficient querying of recent views
            $table->index(['user_id', 'viewed_at']);
            
            // Unique constraint to prevent duplicate entries
            $table->unique(['user_id', 'library_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_views');
    }
};
