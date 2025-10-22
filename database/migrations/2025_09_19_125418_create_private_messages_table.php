<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('private_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('private_conversation_id');
            $table->unsignedBigInteger('sender_id');
            $table->text('content');
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamps();

            $table->foreign('private_conversation_id')->references('id')->on('private_conversations')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['private_conversation_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('private_messages');
    }
};
