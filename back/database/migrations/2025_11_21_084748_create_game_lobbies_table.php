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
        Schema::create('game_lobbies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_game');
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_character');

            $table->foreign('id_game')->references('id')->on('games');
            $table->foreign('id_user')->references('id')->on('users');
            $table->foreign('id_character')->references('id')->on('characters');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_lobbies');
    }
};
