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
        Schema::create('user_lobbies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lobby_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('character_id')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('status')->default(true);

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('character_id')->references('id')->on('characters');
            $table->foreign('lobby_id')->references('id')->on('lobbies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_lobbies');
    }
};
