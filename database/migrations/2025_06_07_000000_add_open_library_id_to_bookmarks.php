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
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->foreignId('open_library_id')->nullable()->after('post_id')->constrained('open_libraries')->onDelete('cascade');
            // Drop and recreate the unique constraint to include open_library_id
            $table->dropUnique(['user_id', 'course_id', 'post_id']);
            $table->unique(['user_id', 'course_id', 'post_id', 'open_library_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropForeign(['open_library_id']);
            $table->dropColumn('open_library_id');
            // Restore the original unique constraint
            $table->dropUnique(['user_id', 'course_id', 'post_id', 'open_library_id']);
            $table->unique(['user_id', 'course_id', 'post_id']);
        });
    }
};