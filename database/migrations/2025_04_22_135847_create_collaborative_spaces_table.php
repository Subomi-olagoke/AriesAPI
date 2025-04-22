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
        Schema::create('collaborative_spaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('channel_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // document, whiteboard, video_project, code, etc.
            $table->json('settings')->nullable();
            $table->uuid('created_by');
            $table->timestamps();
            
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborative_spaces');
    }
};