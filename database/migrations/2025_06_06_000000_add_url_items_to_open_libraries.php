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
        Schema::table('open_libraries', function (Blueprint $table) {
            // Add column for URL items if it doesn't exist
            if (!Schema::hasColumn('open_libraries', 'url_items')) {
                $table->json('url_items')->nullable()->after('keywords')
                    ->comment('Array of URL items with fetched content');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_libraries', function (Blueprint $table) {
            if (Schema::hasColumn('open_libraries', 'url_items')) {
                $table->dropColumn('url_items');
            }
        });
    }
};