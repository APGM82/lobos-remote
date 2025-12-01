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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->enum('type', ['public', 'private', 'group'])->default('public');
            $table->foreignId('recipient_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->json('recipient_ids')->nullable(); // Para mensajes de grupo
            $table->timestamps();

            // Índices para consultas eficientes
            $table->index(['game_id', 'created_at']);
            $table->index(['type', 'game_id']);
            $table->index('recipient_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
