<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            // Existing columns (adjust these as needed)
            $table->string('paystack_reference')->nullable();
            $table->string('plan_type')->nullable();
            $table->boolean('is_active')->default(true);
            
            // New columns for recurring subscriptions
            // Note: When creating a new table, the "after" method is unnecessary since column order is defined by the order of declarations.
            $table->string('paystack_subscription_code')->nullable();
            $table->string('paystack_email_token')->nullable();
            $table->string('plan_code')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->boolean('is_recurring')->default(false);
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
};