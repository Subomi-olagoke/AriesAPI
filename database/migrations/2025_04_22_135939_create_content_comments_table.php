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
        Schema::create('content_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_id');
            $table->uuid('user_id');
            $table->text('comment_text');
            $table->json('position')->nullable(); // For positioning comments in the content
            $table->boolean('resolved')->default(false);
            $table->uuid('parent_id')->nullable(); // For threaded comments
            $table->timestamps();
            
            $table->foreign('content_id')->references('id')->on('collaborative_contents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('content_comments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_comments');
    }
};