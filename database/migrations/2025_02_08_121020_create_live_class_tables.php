<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('live_classes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->char('teacher_id', 36);
            $table->string('meeting_id')->unique();
            $table->json('settings')->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();

            $table->foreign('teacher_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        Schema::create('live_class_participants', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->unsignedBigInteger('live_class_id');
            $table->string('role')->default('participant');
            $table->json('preferences')->nullable();
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('live_class_id')->references('id')->on('live_classes')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('live_class_participants');
        Schema::dropIfExists('live_classes');
    }
};
