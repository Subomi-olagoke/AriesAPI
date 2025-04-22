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
        Schema::create('collaborative_contents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('space_id');
            $table->integer('version')->default(1);
            $table->string('content_type'); // text, image, video, etc.
            $table->longText('content_data')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by');
            $table->timestamps();
            
            $table->foreign('space_id')->references('id')->on('collaborative_spaces')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborative_contents');
    }
};