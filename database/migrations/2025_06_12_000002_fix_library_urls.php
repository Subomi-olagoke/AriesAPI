<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop the table if it exists
        Schema::dropIfExists('library_urls');
        
        // Create the table with the correct column types
        Schema::create('library_urls', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by', 36)->nullable(); // Changed to string for UUID compatibility
            $table->timestamps();
            
            // Use a shorter substring for the index (first 255 chars)
            $table->index(DB::raw('url(255)'));
            
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
};