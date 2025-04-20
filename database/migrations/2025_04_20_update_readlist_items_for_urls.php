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
        Schema::table('readlist_items', function (Blueprint $table) {
            // Make the morphable fields nullable
            $table->string('item_id')->nullable()->change();
            $table->string('item_type')->nullable()->change();
            
            // Add fields for URL-based items
            $table->string('title')->nullable()->after('notes');
            $table->text('description')->nullable()->after('title');
            $table->string('url')->nullable()->after('description');
            $table->string('type')->nullable()->after('url'); // 'link', 'pdf', 'video', etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readlist_items', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'url', 'type']);
            
            // Restore the non-nullable constraints
            $table->string('item_id')->nullable(false)->change();
            $table->string('item_type')->nullable(false)->change();
        });
    }
};