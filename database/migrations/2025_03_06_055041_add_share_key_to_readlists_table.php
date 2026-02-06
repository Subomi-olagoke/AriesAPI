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
        Schema::table('readlists', function (Blueprint $table) {
            $table->string('share_key', 10)->nullable()->unique()->after('is_public');
        });
        
        // Generate share keys for existing readlists
        DB::table('readlists')->whereNull('share_key')->cursor()->each(function ($readlist) {
            DB::table('readlists')
                ->where('id', $readlist->id)
                ->update(['share_key' => Str::random(10)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readlists', function (Blueprint $table) {
            $table->dropColumn('share_key');
        });
    }
};