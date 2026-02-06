<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;



return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void {
		Schema::create('users', function (Blueprint $table) {
			$table->uuid('id')->primary();
			$table->string('first_name');
			$table->string('last_name');
			$table->string('username')->unique();
            $table->enum('role', ['educator', 'learner', 'explorer'])->default('explorer');
			$table->string('avatar')->nullable();
			$table->string('verification_code')->nullable();
			$table->string('email')->unique();
			$table->timestamp('email_verified_at')->nullable();
			$table->string('password');
			$table->rememberToken();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void {
        if (Schema::hasTable('user_topic')) {
            Schema::table('user_topic', function (Blueprint $table) {
                // Drop the foreign key constraints using the correct table and constraint names
                $table->dropForeign('user_topic_user_id_foreign');
                $table->dropForeign('user_topic_topic_id_foreign');
            });
        }

		Schema::dropIfExists('users');
	}
};
