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
        Schema::table('game_lobbies', function (Blueprint $table) {
            $table->boolean('is_alive')->default(true)->after('id_character');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_lobbies', function (Blueprint $table) {
            $table->dropColumn('is_alive');
        });
    }
};
