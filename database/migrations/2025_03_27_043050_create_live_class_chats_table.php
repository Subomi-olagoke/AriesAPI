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
        Schema::create('live_class_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('live_class_id');
            $table->char('user_id', 36);
            $table->text('message');
            $table->string('type')->default('text'); // 'text', 'system', 'file', etc.
            $table->json('metadata')->nullable(); // JSON field for additional information
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('live_class_id')->references('id')->on('live_classes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Index for better performance
            $table->index(['live_class_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_class_chats');
    }
};
