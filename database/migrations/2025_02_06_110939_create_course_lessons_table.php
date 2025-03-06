<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('section_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('content_type')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('order')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->string('video_url')->nullable();
            $table->string('file_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->json('quiz_data')->nullable();
            $table->json('assignment_data')->nullable();
            $table->timestamps();

            $table->foreign('section_id')
                  ->references('id')
                  ->on('course_sections')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
    }
};