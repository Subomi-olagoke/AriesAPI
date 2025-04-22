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
        Schema::create('content_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_id');
            $table->uuid('user_id')->nullable(); // Null means it applies to all users
            $table->enum('role', ['owner', 'editor', 'commenter', 'viewer'])->default('viewer');
            $table->uuid('granted_by');
            $table->timestamps();
            
            $table->foreign('content_id')->references('id')->on('collaborative_contents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['content_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_permissions');
    }
};