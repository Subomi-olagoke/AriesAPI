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
        // Add payment_method to course_enrollments table
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('payment_method')->default('paystack')->after('transaction_reference');
            $table->integer('points_used')->nullable()->after('payment_method');
        });

        // Add payment_method to hire_requests table
        Schema::table('hire_requests', function (Blueprint $table) {
            $table->string('payment_method')->default('paystack')->after('transaction_reference');
            $table->integer('points_used')->nullable()->after('payment_method');
        });

        // Add payment_method to payment_logs table if it exists
        if (Schema::hasTable('payment_logs')) {
            Schema::table('payment_logs', function (Blueprint $table) {
                $table->string('payment_method')->default('paystack')->after('payment_type');
                $table->integer('points_used')->nullable()->after('payment_method');
            });
        }

        // Add points_to_currency_rate to alex_points_levels table
        Schema::table('alex_points_levels', function (Blueprint $table) {
            $table->decimal('points_to_currency_rate', 10, 4)->nullable()->after('rewards');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove payment_method from course_enrollments table
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
            $table->dropColumn('points_used');
        });

        // Remove payment_method from hire_requests table
        Schema::table('hire_requests', function (Blueprint $table) {
            $table->dropColumn('payment_method');
            $table->dropColumn('points_used');
        });

        // Remove payment_method from payment_logs table if it exists
        if (Schema::hasTable('payment_logs')) {
            Schema::table('payment_logs', function (Blueprint $table) {
                $table->dropColumn('payment_method');
                $table->dropColumn('points_used');
            });
        }

        // Remove points_to_currency_rate from alex_points_levels table
        Schema::table('alex_points_levels', function (Blueprint $table) {
            $table->dropColumn('points_to_currency_rate');
        });
    }
};