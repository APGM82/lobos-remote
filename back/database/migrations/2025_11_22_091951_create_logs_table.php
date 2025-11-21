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
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_game_lobby');
            $table->integer('turn');
            $table->integer('fase');
            $table->unsignedBigInteger('action_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('affected_user_id');
            $table->timestamps();

            $table->foreign('id_game_lobby')->references('id')->on('game_lobbies');
            $table->foreign('action_id')->references('id')->on('actions');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('affected_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
