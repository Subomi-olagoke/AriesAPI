<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixLibraryUrlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('library_urls', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by', 36)->nullable(); // Changed to string for UUID compatibility
            $table->timestamps();
            
            // Index on URL for faster lookups
            $table->index('url');
            
            // Foreign key with correct UUID type
            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('library_urls');
    }
}