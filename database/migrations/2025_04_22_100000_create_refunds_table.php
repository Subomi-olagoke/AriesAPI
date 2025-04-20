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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_log_id')->constrained()->onDelete('cascade');
            $table->string('transaction_reference');
            $table->text('reason')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->string('processor')->default('admin'); // admin, system, api
            $table->string('processor_id')->nullable(); // ID of the admin who processed the refund
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            
            $table->index('transaction_reference');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};