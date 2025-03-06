<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the table if it exists before creating it
        Schema::dropIfExists('lesson_progress');
        
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->unsignedBigInteger('lesson_id');
            $table->boolean('completed')->default(false);
            $table->integer('watched_seconds')->nullable();
            $table->timestamp('last_watched_at')->nullable();
            $table->json('quiz_answers')->nullable();
            $table->json('assignment_submission')->nullable();
            $table->timestamps();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('lesson_id')
                ->references('id')
                ->on('course_lessons')
                ->onDelete('cascade');
            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};