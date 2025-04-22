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
        Schema::create('hire_session_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hire_session_id');
            $table->uuid('user_id');
            $table->string('title');
            $table->string('file_path');
            $table->string('file_type');
            $table->bigInteger('file_size')->unsigned();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('shared_at')->useCurrent();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('hire_session_id')->references('id')->on('hire_sessions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hire_session_documents');
    }
};
