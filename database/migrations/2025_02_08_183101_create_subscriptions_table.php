<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add new columns for recurring subscriptions
            if (!Schema::hasColumn('subscriptions', 'paystack_subscription_code')) {
                $table->string('paystack_subscription_code')->nullable()->after('paystack_reference');
            }
            
            if (!Schema::hasColumn('subscriptions', 'paystack_email_token')) {
                $table->string('paystack_email_token')->nullable()->after('paystack_subscription_code');
            }
            
            if (!Schema::hasColumn('subscriptions', 'plan_code')) {
                $table->string('plan_code')->nullable()->after('plan_type');
            }
            
            if (!Schema::hasColumn('subscriptions', 'amount')) {
                $table->decimal('amount', 10, 2)->default(0)->after('plan_code');
            }
            
            if (!Schema::hasColumn('subscriptions', 'status')) {
                $table->string('status')->default('pending')->after('amount');
            }
            
            if (!Schema::hasColumn('subscriptions', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('is_active');
            }
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'paystack_subscription_code',
                'paystack_email_token',
                'plan_code',
                'amount',
                'status',
                'is_recurring'
            ]);
        });
    }
};