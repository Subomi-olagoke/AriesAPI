<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('course_enrollments', function (Blueprint $table) {
        $table->id();
        $table->uuid('user_id');
        $table->unsignedBigInteger('course_id');
        $table->string('transaction_reference')->nullable();
        $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
        $table->decimal('progress', 5, 2)->default(0.00);
        $table->timestamps();
        
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        
        // Prevent duplicate enrollments
        $table->unique(['user_id', 'course_id']);
    });
}

public function down()
{
    Schema::dropIfExists('course_enrollments');
}
};
