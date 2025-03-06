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
        Schema::create('readlist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('readlist_id');
            $table->morphs('item'); // Creates item_id and item_type columns
            $table->integer('order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('readlist_id')
                  ->references('id')
                  ->on('readlists')
                  ->onDelete('cascade');
            
            // Ensure the same item cannot be added twice to a readlist
            $table->unique(['readlist_id', 'item_id', 'item_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('readlist_items');
    }
};