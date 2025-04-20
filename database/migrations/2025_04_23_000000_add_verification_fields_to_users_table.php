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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_status')->default('pending'); // pending, in_review, approved, rejected
            $table->text('verification_notes')->nullable();
            $table->json('verification_documents')->nullable();
        });

        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('document_type'); // id_card, degree, certificate, etc.
            $table->string('document_url');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('notes')->nullable();
            $table->string('verified_by')->nullable(); // admin who verified
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_verified',
                'verified_at',
                'verification_status',
                'verification_notes',
                'verification_documents'
            ]);
        });

        Schema::dropIfExists('verification_requests');
    }
};