<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->uuid('user_id');
        $table->string('paystack_reference')->nullable();
        $table->string('plan_type');
        $table->boolean('is_active')->default(false);
        $table->timestamp('starts_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
        
        $table->foreign('user_id')
              ->references('id')
              ->on('users')
              ->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('subscriptions');
}
};
