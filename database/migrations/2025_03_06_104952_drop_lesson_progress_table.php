<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DropLessonProgressTable extends Migration
{
    public function up()
    {
        Schema::dropIfExists('lesson_progress');
    }

    public function down()
    {
        // Recreate the table if needed
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
        });
    }
}