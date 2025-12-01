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
        Schema::create('vite_config', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Clave de la variable (ej: VITE_REVERB_APP_KEY)
            $table->text('value'); // Valor de la variable
            $table->string('description')->nullable(); // Descripción opcional
            $table->boolean('active')->default(true); // Si está activa o no
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vite_config');
    }
};
