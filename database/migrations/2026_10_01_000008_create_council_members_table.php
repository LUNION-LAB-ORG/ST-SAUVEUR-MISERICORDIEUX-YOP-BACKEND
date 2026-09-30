<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membres d'un conseil (nom, fonction, téléphone).
 * Le téléphone n'est jamais exposé publiquement : back-office uniquement.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('council_members')) {
            return;
        }

        Schema::create('council_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_id')->constrained('councils')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('function', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['council_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_members');
    }
};
