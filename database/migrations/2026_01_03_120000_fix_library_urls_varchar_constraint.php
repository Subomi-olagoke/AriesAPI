<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixLibraryUrlsVarcharConstraint extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('library_urls', function (Blueprint $table) {
            // Change title, summary and notes from VARCHAR(255) to TEXT
            // This fixes the "String data, right truncated" error
            $table->text('title')->nullable()->change();
            $table->text('summary')->nullable()->change();
            $table->text('notes')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('library_urls', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->change();
            $table->string('summary', 255)->nullable()->change();
            $table->string('notes', 255)->nullable()->change();
        });
    }
}
