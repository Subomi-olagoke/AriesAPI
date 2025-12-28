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
            if (!Schema::hasColumn('open_libraries', 'creator_id')) {
                $table->string('creator_id', 36)->nullable()->after('id');
                $table->index('creator_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_libraries', function (Blueprint $table) {
            if (Schema::hasColumn('open_libraries', 'creator_id')) {
                $table->dropIndex(['creator_id']);
                $table->dropColumn('creator_id');
            }
        });
    }
};
