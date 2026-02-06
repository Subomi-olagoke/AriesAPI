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
        Schema::table('library_content', function (Blueprint $table) {
            // Speed up queries that fetch all content for a library
            $table->index('library_id', 'idx_library_content_library_id');
            
            // Speed up polymorphic lookups
            $table->index(['content_type', 'content_id'], 'idx_library_content_type_id');
        });

        Schema::table('library_url_votes', function (Blueprint $table) {
            // Speed up vote counting and aggregation queries
            $table->index('library_url_id', 'idx_votes_library_url_id');
            
            // Speed up user-specific vote lookups
            $table->index(['user_id', 'library_url_id'], 'idx_votes_user_url');
        });

        Schema::table('comments', function (Blueprint $table) {
            // Speed up comment counting for library URLs
            $table->index(['commentable_type', 'commentable_id'], 'idx_comments_type_id');
        });

        Schema::table('library_follows', function (Blueprint $table) {
            // Speed up follow status checks
            $table->index(['library_id', 'user_id'], 'idx_follows_library_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('library_content', function (Blueprint $table) {
            $table->dropIndex('idx_library_content_library_id');
            $table->dropIndex('idx_library_content_type_id');
        });

        Schema::table('library_url_votes', function (Blueprint $table) {
            $table->dropIndex('idx_votes_library_url_id');
            $table->dropIndex('idx_votes_user_url');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('idx_comments_type_id');
        });

        Schema::table('library_follows', function (Blueprint $table) {
            $table->dropIndex('idx_follows_library_user');
        });
    }
};
