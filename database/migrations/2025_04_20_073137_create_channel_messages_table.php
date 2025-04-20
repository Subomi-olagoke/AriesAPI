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
        Schema::create('channel_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id');
            $table->uuid('sender_id');
            $table->text('body');
            $table->string('attachment')->nullable();
            $table->string('attachment_type')->nullable();
            $table->json('read_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_messages');
    }
};
