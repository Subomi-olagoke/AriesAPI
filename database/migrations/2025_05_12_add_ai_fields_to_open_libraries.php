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
        Schema::table('open_libraries', function (Blueprint $table) {
            // Add new columns for AI integration
            $table->text('cover_prompt')->nullable()->after('thumbnail_url');
            $table->string('cover_image_url')->nullable()->after('thumbnail_url');
            $table->json('keywords')->nullable()->after('criteria');
            $table->boolean('ai_generated')->default(false)->after('has_ai_cover');
            $table->timestamp('ai_generation_date')->nullable()->after('ai_generated');
            $table->string('ai_model_used')->nullable()->after('ai_generation_date');
            $table->text('rejection_reason')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('open_libraries', function (Blueprint $table) {
            $table->dropColumn([
                'cover_prompt',
                'cover_image_url',
                'keywords',
                'ai_generated',
                'ai_generation_date',
                'ai_model_used',
                'rejection_reason'
            ]);
        });
    }
};