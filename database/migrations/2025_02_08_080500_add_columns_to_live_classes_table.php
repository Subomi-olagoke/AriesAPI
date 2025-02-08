<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('live_classes', function (Blueprint $table) {
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('teacher_id');
            $table->dateTime('scheduled_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('status')->default('scheduled');
            
            $table->foreign('teacher_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('live_classes', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn(['title', 'description', 'teacher_id', 'scheduled_at', 'ended_at', 'status']);
        });
    }
};
