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
            if (!Schema::hasColumn('open_libraries', 'cover_image_url')) {
                $table->string('cover_image_url')->nullable()->after('thumbnail_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_libraries', function (Blueprint $table) {
            if (Schema::hasColumn('open_libraries', 'cover_image_url')) {
                $table->dropColumn('cover_image_url');
            }
        });
    }
};
