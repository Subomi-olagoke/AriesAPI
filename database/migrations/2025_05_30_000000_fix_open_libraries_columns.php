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
            // Add the missing columns if they don't exist
            if (!Schema::hasColumn('open_libraries', 'name')) {
                $table->string('name');
            }
            
            if (!Schema::hasColumn('open_libraries', 'description')) {
                $table->text('description')->nullable();
            }
            
            if (!Schema::hasColumn('open_libraries', 'type')) {
                $table->string('type')->default('auto'); // auto, course, or auto_cogni
            }
            
            if (!Schema::hasColumn('open_libraries', 'thumbnail_url')) {
                $table->string('thumbnail_url')->nullable();
            }
            
            if (!Schema::hasColumn('open_libraries', 'course_id')) {
                $table->unsignedBigInteger('course_id')->nullable();
            }
            
            if (!Schema::hasColumn('open_libraries', 'criteria')) {
                $table->json('criteria')->nullable();
            }
            
            // Add approval fields if they don't exist
            if (!Schema::hasColumn('open_libraries', 'is_approved')) {
                $table->boolean('is_approved')->default(false);
            }
            
            if (!Schema::hasColumn('open_libraries', 'approval_status')) {
                $table->string('approval_status')->default('pending');
            }
            
            if (!Schema::hasColumn('open_libraries', 'approval_date')) {
                $table->timestamp('approval_date')->nullable();
            }
            
            if (!Schema::hasColumn('open_libraries', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            
            if (!Schema::hasColumn('open_libraries', 'has_ai_cover')) {
                $table->boolean('has_ai_cover')->default(false);
            }
        });
        
        // Create indexes if they don't exist
        if (!Schema::hasIndex('open_libraries', 'idx_open_libraries_approval_status')) {
            Schema::table('open_libraries', function (Blueprint $table) {
                $table->index('approval_status', 'idx_open_libraries_approval_status');
            });
        }
        
        if (!Schema::hasIndex('open_libraries', 'idx_open_libraries_is_approved')) {
            Schema::table('open_libraries', function (Blueprint $table) {
                $table->index('is_approved', 'idx_open_libraries_is_approved');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a fix migration, so we don't want to remove the columns in the down method
    }
};