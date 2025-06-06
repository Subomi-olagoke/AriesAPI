<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    if (!Schema::hasTable('conversations')) {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_one_id');
            $table->uuid('user_two_id');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('user_one_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_two_id')->references('id')->on('users')->onDelete('cascade');
            
            // Ensure uniqueness of conversation between two users
            $table->unique(['user_one_id', 'user_two_id']);
        });
    }
}

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};

