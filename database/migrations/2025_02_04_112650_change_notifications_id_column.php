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
            Schema::table('notifications', function (Blueprint $table) {
                // Drop the existing primary key
                $table->dropPrimary();

                // Change the 'id' column type to UUID (CHAR(36))
                $table->uuid('id')->primary()->change();
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::table('notifications', function (Blueprint $table) {
                // Revert the 'id' column to its original type
                $table->bigIncrements('id')->change();

                // Add back the original primary key
                $table->primary('id');
            });
        }

};
