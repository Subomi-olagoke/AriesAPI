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
            $table->string('meeting_id')->nullable()->after('transaction_reference');
            $table->timestamp('video_session_started_at')->nullable()->after('meeting_id');
            $table->timestamp('video_session_ended_at')->nullable()->after('video_session_started_at');
            $table->string('video_session_status')->default('pending')->after('video_session_ended_at');
            $table->json('video_session_settings')->nullable()->after('video_session_status');
            $table->string('recording_url')->nullable()->after('video_session_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hire_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'meeting_id',
                'video_session_started_at',
                'video_session_ended_at',
                'video_session_status',
                'video_session_settings',
                'recording_url'
            ]);
        });
    }
};
