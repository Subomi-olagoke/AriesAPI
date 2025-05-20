<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCogniChatTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cogni_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('share_key')->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('cogni_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('cogni_chats')->onDelete('cascade');
            $table->enum('sender_type', ['user', 'cogni']);
            $table->enum('content_type', ['text', 'link', 'image', 'document']);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cogni_chat_messages');
        Schema::dropIfExists('cogni_chats');
    }
}