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
        Schema::create('operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_id');
            $table->uuid('user_id');
            $table->enum('type', ['insert', 'delete', 'format', 'cursor', 'selection'])->default('insert');
            $table->integer('position')->nullable();
            $table->integer('length')->nullable();
            $table->text('text')->nullable();
            $table->integer('version');
            $table->json('meta')->nullable(); // Additional properties for format, cursor, etc.
            $table->timestamps();
            
            $table->foreign('content_id')->references('id')->on('collaborative_contents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};