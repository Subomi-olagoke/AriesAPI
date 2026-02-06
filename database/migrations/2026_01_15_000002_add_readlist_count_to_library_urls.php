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
        Schema::table('library_urls', function (Blueprint $table) {
            $table->unsignedInteger('readlist_count')->default(0)->after('notes');
        });

        // Update existing counts based on readlist_items
        DB::statement("
            UPDATE library_urls
            SET readlist_count = (
                SELECT COUNT(*)
                FROM readlist_items
                WHERE readlist_items.url = library_urls.url
                AND readlist_items.type = 'url'
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('library_urls', function (Blueprint $table) {
            $table->dropColumn('readlist_count');
        });
    }
};
