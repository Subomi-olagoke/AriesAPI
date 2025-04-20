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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type'); // monthly, yearly
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->string('paystack_plan_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Add a new column to subscriptions table to reference the plan
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('user_id')
                ->constrained('subscription_plans')->nullOnDelete();
            
            // Add ability to create channels to subscriptions
            $table->boolean('can_create_channels')->default(false)->after('is_recurring');
            
            // Add credits system for points redemption
            $table->integer('available_credits')->default(0)->after('can_create_channels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn(['subscription_plan_id', 'can_create_channels', 'available_credits']);
        });
        
        Schema::dropIfExists('subscription_plans');
    }
};
