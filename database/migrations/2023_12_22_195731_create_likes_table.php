<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('likes', function (Blueprint $table) {
			$table->id();
			$table->foreignId('post_id')->constrained('posts')->onUpdate('cascade');
            $table->foreignId('comment_id')->constrained('comments')->onUpdate('cascade');
            $table->foreignId('course_id')->constrained('courses')->onUpdate('cascade');
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
		Schema::dropIfExists('likes');
	}
};
