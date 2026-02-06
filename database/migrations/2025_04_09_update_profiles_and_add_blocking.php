<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add share_key to profiles table
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('share_key', 20)->nullable()->unique()->after('social_links');
        });
        
        // Generate share keys for existing profiles
        DB::table('profiles')->whereNull('share_key')->cursor()->each(function ($profile) {
            DB::table('profiles')
                ->where('id', $profile->id)
                ->update(['share_key' => Str::random(20)]);
        });

        // Create user_blocks table
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('blocked_user_id');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('blocked_user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['user_id', 'blocked_user_id']);
        });
        
        // Create user_mutes table
        Schema::create('user_mutes', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('muted_user_id');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('muted_user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unique(['user_id', 'muted_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('share_key');
        });
        
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('user_mutes');
    }
};