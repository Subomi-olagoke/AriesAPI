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
        Schema::create('cogni_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('conversation_id');
            $table->text('question');
            $table->text('answer');
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index(['user_id', 'conversation_id']);
            $table->index('conversation_id');
            $table->index('created_at');
            
            // Add foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cogni_conversations');
    }
};
