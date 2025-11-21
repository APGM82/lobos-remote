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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_user_host');
            $table->string("code_join_to");
            $table->unsignedBigInteger('code_status');
            $table->string('name');
            $table->integer('max_players');
            $table->timestamps();

            $table->foreign('id_user_host')->references('id')->on('users');
            $table->foreign('code_status')->references('id')->on('status_codes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
