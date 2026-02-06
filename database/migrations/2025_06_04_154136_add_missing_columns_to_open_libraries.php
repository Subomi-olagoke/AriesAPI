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
            // Check and add necessary columns
            if (!Schema::hasColumn('open_libraries', 'approval_status')) {
                $table->string('approval_status')->default('pending');
            }

            if (!Schema::hasColumn('open_libraries', 'is_approved')) {
                $table->boolean('is_approved')->default(false);
            }

            if (!Schema::hasColumn('open_libraries', 'approval_date')) {
                $table->timestamp('approval_date')->nullable();
            }

            if (!Schema::hasColumn('open_libraries', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->foreign('approved_by')->references('id')->on('users');
            }

            if (!Schema::hasColumn('open_libraries', 'has_ai_cover')) {
                $table->boolean('has_ai_cover')->default(false);
            }

            if (!Schema::hasColumn('open_libraries', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
        });

        // Add indexes if they don't exist
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
        // No rollback needed as this is a fix migration
    }
};
