<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('open_libraries', function (Blueprint $table) {
            $table->string('share_key', 12)->nullable()->unique()->after('id');
        });

        // Generate share keys for existing libraries
        $libraries = \App\Models\OpenLibrary::whereNull('share_key')->get();
        foreach ($libraries as $library) {
            $library->share_key = Str::random(12);
            $library->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_libraries', function (Blueprint $table) {
            $table->dropColumn('share_key');
        });
    }
};
