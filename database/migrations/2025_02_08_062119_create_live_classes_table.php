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
            $table->dateTime('scheduled_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
            
            $table->foreign('teacher_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('live_classes');
    }
};
