<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First add the new polymorphic columns
        Schema::table('likes', function (Blueprint $table) {
            $table->string('likeable_type')->nullable()->after('id');
            $table->unsignedBigInteger('likeable_id')->nullable()->after('likeable_type');
            $table->index(['likeable_type', 'likeable_id']);
        });

        // Migrate existing comment likes
        DB::statement("
            UPDATE likes 
            SET likeable_type = 'App\\\\Models\\\\Comment', 
                likeable_id = comment_id 
            WHERE comment_id IS NOT NULL
        ");

        // Migrate existing post likes
        DB::statement("
            UPDATE likes 
            SET likeable_type = 'App\\\\Models\\\\Post', 
                likeable_id = post_id 
            WHERE post_id IS NOT NULL AND comment_id IS NULL
        ");

        // Migrate existing course likes
        DB::statement("
            UPDATE likes 
            SET likeable_type = 'App\\\\Models\\\\Course', 
                likeable_id = course_id 
            WHERE course_id IS NOT NULL AND comment_id IS NULL AND post_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('likes', function (Blueprint $table) {
            $table->dropIndex(['likeable_type', 'likeable_id']);
            $table->dropColumn('likeable_type');
            $table->dropColumn('likeable_id');
        });
    }
};