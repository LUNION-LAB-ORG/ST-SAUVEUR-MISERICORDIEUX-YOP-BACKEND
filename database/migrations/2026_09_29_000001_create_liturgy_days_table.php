<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liturgie du jour importée depuis l'API AELF (une ligne par date).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('liturgy_days')) {
            return;
        }

        Schema::create('liturgy_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('zone', 50)->nullable();
            $table->string('feast')->nullable();
            $table->string('degree', 100)->nullable();
            $table->string('color', 50)->nullable();
            // Lectures nettoyées (liste d'objets type/ref/title/intro/content/...)
            $table->json('readings')->nullable();
            // Surcharges saisies par l'administrateur
            $table->string('feast_override')->nullable();
            $table->string('color_override', 50)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liturgy_days');
    }
};
