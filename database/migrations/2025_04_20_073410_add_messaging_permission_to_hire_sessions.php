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
        Schema::table('hire_sessions', function (Blueprint $table) {
            $table->boolean('can_message')->default(true)->after('payment_status');
            $table->uuid('conversation_id')->nullable()->after('can_message');
            
            // Add foreign key to conversations table
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
        });
        
        // Add a column to conversations to track hire sessions
        Schema::table('conversations', function (Blueprint $table) {
            $table->uuid('hire_session_id')->nullable()->after('user_two_id');
            $table->boolean('is_restricted')->default(false)->after('hire_session_id');
            
            // Add foreign key to hire_sessions table
            $table->foreign('hire_session_id')->references('id')->on('hire_sessions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['hire_session_id']);
            $table->dropColumn(['hire_session_id', 'is_restricted']);
        });
        
        Schema::table('hire_sessions', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['can_message', 'conversation_id']);
        });
    }
};
